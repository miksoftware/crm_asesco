<?php

namespace App\Services;

use App\Events\MessageStatusUpdated;
use App\Events\NewWhatsAppMessage;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\LidMapping;
use App\Models\Message;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Servicio para procesar eventos recibidos del WebSocket de Evolution API.
 * 
 * Este servicio es invocado por el comando EvolutionListenCommand
 * y reutiliza la lógica existente de MessageService + WebhookController
 * para procesar mensajes, pero además emite eventos de Broadcasting
 * para actualizar el frontend en tiempo real.
 */
class EvolutionWebSocketService
{
    public function __construct(
        private MessageService $messageService,
        private NotificationService $notificationService,
    ) {}

    /**
     * Procesar un evento recibido del WebSocket de Evolution API.
     * Retorna true si el evento fue procesado, false si fue ignorado/duplicado.
     */
    public function processEvent(string $event, array $data): bool
    {
        // Normalizar nombre del evento
        $event = strtoupper(str_replace('.', '_', $event));

        return match ($event) {
            'MESSAGES_UPSERT' => $this->handleMessagesUpsert($data),
            'MESSAGES_UPDATE' => $this->handleMessagesUpdate($data),
            'SEND_MESSAGE' => $this->handleSendMessage($data),
            'CONNECTION_UPDATE' => $this->handleConnectionUpdate($data),
            default => false,
        };
    }

    /**
     * Verificar si un mensaje ya fue procesado (deduplicación Webhook + WebSocket).
     * Usa cache con TTL de 60 segundos para evitar duplicados.
     */
    private function isDuplicate(string $messageId, string $prefix = 'ws'): bool
    {
        $cacheKey = "msg_processed:{$prefix}:{$messageId}";

        if (Cache::has($cacheKey)) {
            Log::debug("Mensaje duplicado ignorado [{$prefix}]", ['message_id' => $messageId]);
            return true;
        }

        // Marcar como procesado por 60 segundos
        Cache::put($cacheKey, true, 60);
        return false;
    }

    /**
     * Procesar MESSAGES_UPSERT - Mensaje nuevo entrante.
     */
    private function handleMessagesUpsert(array $data): bool
    {
        $instanceName = $data['instance'] ?? null;
        $messageData = $data['data'] ?? $data;

        if (!$instanceName) {
            Log::warning('WS: MESSAGES_UPSERT sin instance name');
            return false;
        }

        // Verificar que la instancia existe en nuestro sistema
        $channel = Channel::where('instance_name', $instanceName)->first();
        if (!$channel) {
            return false;
        }

        // Extraer message ID para deduplicación
        $externalMessageId = $messageData['key']['id'] ?? null;
        if (!$externalMessageId) {
            return false;
        }

        // Deduplicación: verificar si ya fue procesado por webhook o ws previo
        if ($this->isDuplicate($externalMessageId, 'upsert')) {
            return false;
        }

        // También verificar si ya existe en la base de datos
        $existingMessage = Message::where('message_id', $externalMessageId)->first();
        if ($existingMessage) {
            Log::debug('WS: Mensaje ya existe en BD', ['message_id' => $externalMessageId]);
            // Marcar en cache para que el webhook también lo ignore
            Cache::put("msg_processed:upsert:{$externalMessageId}", true, 60);
            return false;
        }

        // Auto-crear LID mapping si hay remoteJidAlt
        $remoteJid = $messageData['key']['remoteJid'] ?? null;
        $remoteJidAlt = $messageData['key']['remoteJidAlt'] ?? null;
        if ($remoteJid && $remoteJidAlt && $remoteJid !== $remoteJidAlt) {
            $this->createLidMapping($remoteJid, $remoteJidAlt, $instanceName);
        }

        try {
            // Reutilizar la lógica existente de MessageService
            $webhookPayload = [
                'instance' => $instanceName,
                'data' => $messageData,
            ];

            $message = $this->messageService->processIncomingMessage($webhookPayload);

            if ($message) {
                // Solo emitir para mensajes nuevos, no para deduplicados
                if ($message->wasRecentlyCreated) {
                    // Crear notificación
                    $this->notificationService->createMessageNotification($message);

                    // Emitir evento de Broadcasting para el frontend
                    broadcast(new NewWhatsAppMessage($message));
                }

                Log::info('WS: Mensaje procesado', [
                    'message_id' => $message->id,
                    'contact_id' => $message->contact_id,
                    'direction' => $message->direction,
                    'is_new' => $message->wasRecentlyCreated,
                ]);

                return true;
            }
        } catch (\Exception $e) {
            Log::error('WS: Error procesando MESSAGES_UPSERT', [
                'error' => $e->getMessage(),
                'instance' => $instanceName,
                'message_id' => $externalMessageId,
            ]);
        }

        return false;
    }

    /**
     * Procesar MESSAGES_UPDATE - Actualización de estado de mensaje.
     */
    private function handleMessagesUpdate(array $data): bool
    {
        $instanceName = $data['instance'] ?? null;
        $updateData = $data['data'] ?? $data;

        $messageId = $updateData['keyId'] ?? $updateData['key']['id'] ?? null;
        $remoteJid = $updateData['remoteJid'] ?? $updateData['key']['remoteJid'] ?? null;
        $status = $updateData['status'] ?? null;

        if (!$messageId || !$status) {
            return false;
        }

        // Deduplicación por messageId + status
        $dedupeKey = "{$messageId}:{$status}";
        if ($this->isDuplicate($dedupeKey, 'status')) {
            return false;
        }

        // Procesar LID mapping si aplica
        if ($remoteJid && str_contains($remoteJid, '@lid') && $instanceName) {
            $this->processLidFromStatus($messageId, $remoteJid, $instanceName);
        }

        // Mapear estado de Evolution API a nuestro sistema
        $mappedStatus = match ($status) {
            'DELIVERY_ACK', 'PLAYED' => 'delivered',
            'READ' => 'read',
            'PENDING' => 'pending',
            'SERVER_ACK' => 'sent',
            default => null,
        };

        if (!$mappedStatus) {
            return false;
        }

        // Actualizar en BD
        $message = Message::where('message_id', $messageId)->first();
        if (!$message) {
            return false;
        }

        $message->update(['status' => $mappedStatus]);

        // Emitir evento de Broadcasting para actualizar checks en el frontend
        broadcast(new MessageStatusUpdated(
            messageId: $message->id,
            externalMessageId: $messageId,
            status: $mappedStatus,
            contactId: $message->contact_id,
            channelId: $message->channel_id,
        ));

        Log::debug('WS: Estado de mensaje actualizado', [
            'message_id' => $messageId,
            'status' => $mappedStatus,
        ]);

        return true;
    }

    /**
     * Procesar SEND_MESSAGE - Mensaje enviado desde otra sesión.
     */
    private function handleSendMessage(array $data): bool
    {
        $instanceName = $data['instance'] ?? null;
        $messageData = $data['data'] ?? $data;

        $externalMessageId = $messageData['key']['id'] ?? null;
        if (!$externalMessageId) {
            return false;
        }

        // Si ya existe en BD (enviado desde nuestro CRM), solo actualizar estado
        $existingMessage = Message::where('message_id', $externalMessageId)->first();
        if ($existingMessage) {
            if ($existingMessage->status === 'pending') {
                $existingMessage->update(['status' => 'sent']);
                broadcast(new MessageStatusUpdated(
                    messageId: $existingMessage->id,
                    externalMessageId: $externalMessageId,
                    status: 'sent',
                    contactId: $existingMessage->contact_id,
                    channelId: $existingMessage->channel_id,
                ));
            }
            return true;
        }

        // Si no existe, es un mensaje enviado desde el teléfono directamente
        // Procesarlo como mensaje saliente
        if ($this->isDuplicate($externalMessageId, 'send')) {
            return false;
        }

        // Reutilizar processIncomingMessage que ya maneja fromMe
        try {
            $webhookPayload = [
                'instance' => $instanceName,
                'data' => $messageData,
            ];
            $message = $this->messageService->processIncomingMessage($webhookPayload);
            if ($message) {
                broadcast(new NewWhatsAppMessage($message));
                return true;
            }
        } catch (\Exception $e) {
            Log::error('WS: Error procesando SEND_MESSAGE', [
                'error' => $e->getMessage(),
                'message_id' => $externalMessageId,
            ]);
        }

        return false;
    }

    /**
     * Procesar CONNECTION_UPDATE - Cambio de estado de conexión.
     */
    private function handleConnectionUpdate(array $data): bool
    {
        $instanceName = $data['instance'] ?? null;
        $state = $data['data']['state'] ?? $data['state'] ?? null;

        if (!$instanceName || !$state) {
            return false;
        }

        $channel = Channel::where('instance_name', $instanceName)->first();
        if (!$channel) {
            return false;
        }

        $mappedStatus = match (strtolower($state)) {
            'open', 'connected' => 'connected',
            'connecting' => 'connecting',
            'close', 'disconnected' => 'disconnected',
            default => null,
        };

        if ($mappedStatus && $channel->status !== $mappedStatus) {
            $channel->update(['status' => $mappedStatus]);
            Log::info('WS: Estado de conexión actualizado', [
                'instance' => $instanceName,
                'status' => $mappedStatus,
            ]);
        }

        return true;
    }

    /**
     * Crear LID mapping desde remoteJid y remoteJidAlt.
     */
    private function createLidMapping(string $remoteJid, string $remoteJidAlt, ?string $instanceName): void
    {
        $jidPart = explode('@', $remoteJid)[0];
        $altPart = explode('@', $remoteJidAlt)[0];
        $cleanJid = explode(':', $jidPart)[0];
        $cleanAlt = explode(':', $altPart)[0];

        // Determinar cuál es el LID y cuál es el teléfono real
        if (str_contains($remoteJid, '@lid')) {
            LidMapping::createMapping($cleanJid, $cleanAlt, null, null);
        } elseif (str_contains($remoteJidAlt, '@lid')) {
            LidMapping::createMapping($cleanAlt, $cleanJid, null, null);
        }
    }

    /**
     * Procesar LID mapping desde actualizaciones de estado.
     */
    private function processLidFromStatus(string $messageId, string $remoteJid, string $instanceName): void
    {
        $lid = explode('@', $remoteJid)[0];
        $phoneNumber = LidMapping::findPhoneByLid($lid);

        if ($phoneNumber) {
            $channel = Channel::where('instance_name', $instanceName)->first();
            if ($channel) {
                // Auto-fix contacto con LID como phone_number
                $lidContact = Contact::where('channel_id', $channel->id)
                    ->where('phone_number', $lid)
                    ->first();

                if ($lidContact) {
                    $realContact = Contact::where('channel_id', $channel->id)
                        ->where('phone_number', $phoneNumber)
                        ->first();

                    if ($realContact && $realContact->id !== $lidContact->id) {
                        Message::where('contact_id', $lidContact->id)
                            ->update(['contact_id' => $realContact->id]);
                        $lidContact->labelRelations()->detach();
                        $lidContact->delete();
                    } elseif (!$realContact) {
                        $lidContact->update([
                            'phone_number' => $phoneNumber,
                            'remote_jid' => $phoneNumber . '@s.whatsapp.net',
                        ]);
                    }
                }
            }
        }
    }
}
