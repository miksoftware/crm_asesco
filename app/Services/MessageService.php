<?php

namespace App\Services;

use App\Jobs\DownloadMediaJob;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Message;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MessageService
{
    public function __construct(
        private EvolutionApiService $evolutionApi
    ) {}

    /**
     * Send a text message via Evolution API.
     * Supports both individual and group messages.
     */
    public function sendTextMessage(int $channelId, string $recipient, string $text, bool $isGroup = false, ?int $userId = null): Message
    {
        $channel = Channel::findOrFail($channelId);

        // Verify channel is connected
        if ($channel->status !== 'connected') {
            throw new \Exception("El canal '{$channel->name}' no está conectado. Estado actual: {$channel->status}");
        }

        if ($isGroup) {
            $contact = Contact::where('channel_id', $channelId)
                ->where('is_group', true)
                ->where('group_jid', $recipient)
                ->firstOrFail();

            $apiRecipient = $recipient;
        } else {
            // Detectar si el recipient es un JID completo (@lid o @s.whatsapp.net)
            $isJid = str_contains($recipient, '@');
            
            if ($isJid) {
                // Buscar contacto por remote_jid (para leads LID)
                $contact = Contact::where('channel_id', $channelId)
                    ->where('remote_jid', $recipient)
                    ->first();
                
                if (!$contact) {
                    // Fallback: buscar por phone_number
                    $phonePart = explode('@', $recipient)[0];
                    $contact = Contact::where('channel_id', $channelId)
                        ->where('phone_number', explode(':', $phonePart)[0])
                        ->first();
                }
                
                if (!$contact) {
                    throw new \Exception('Contacto no encontrado para el JID: ' . $recipient);
                }
                
                $apiRecipient = $recipient;
            } else {
                $contact = Contact::firstOrCreate(
                    [
                        'channel_id' => $channelId,
                        'phone_number' => $this->normalizePhoneNumber($recipient),
                    ],
                    [
                        'name' => null,
                        'push_name' => null,
                        'labels' => [],
                        'metadata' => [],
                    ]
                );

                $apiRecipient = $contact->remote_jid ?? $recipient;

                if ($apiRecipient && !str_contains($apiRecipient, '@')) {
                    $apiRecipient = $this->normalizePhoneNumber($apiRecipient);
                }
            }
        }

        // Crear mensaje con el estado 'pending'
        $message = Message::create([
            'contact_id' => $contact->id,
            'channel_id' => $channelId,
            'user_id' => $userId ?? auth()->id(),
            'message_id' => null, // Se actualizará cuando el Job lo envíe
            'direction' => 'outgoing',
            'type' => 'text',
            'content' => $text,
            'sender_name' => $isGroup ? 'Tú' : null,
            'sender_phone' => $isGroup ? $channel->phone_number : null,
            'status' => 'pending',
            'is_read' => true,
            'sent_at' => now(),
        ]);

        // Despachar el Job en background
        \App\Jobs\SendMessageJob::dispatch(
            messageId: $message->id,
            instanceName: $channel->instance_name,
            recipient: $apiRecipient,
            text: $text,
            type: 'text'
        );

        return $message;
    }



    /**
     * Process an incoming message from Evolution API webhook.
     * Requirements: 9.1
     * 
     * Usa LidResolverService para resolución determinista de LIDs.
     * Si no se puede resolver, crea el contacto con el LID y despacha
     * un Job de resolución activa en background.
     */
    public function processIncomingMessage(array $webhookData): ?Message
    {
        $instanceName = $webhookData['instance'] ?? null;
        $data = $webhookData['data'] ?? [];
        
        // Find channel by instance name
        $channel = Channel::where('instance_name', $instanceName)->firstOrFail();
        
        // Extract message data
        $remoteJid = $data['key']['remoteJid'] ?? '';
        $messageId = $data['key']['id'] ?? null;
        $pushName = $data['pushName'] ?? null;
        $isFromMe = ($data['key']['fromMe'] ?? false) === true;
        
        // ⭐ SKIP outgoing individual messages (fromMe=true)
        $isGroupMessage = str_contains($remoteJid, '@g.us');
        if ($isFromMe && !$isGroupMessage) {
            return null;
        }
        
        // ⭐ GROUP MESSAGE HANDLING
        $senderName = null;
        $senderPhone = null;
        
        if ($isGroupMessage) {
            // For groups, remoteJid is the group JID, participant is the sender
            $participant = $data['key']['participant'] ?? null;
            
            // Extract sender info
            if ($participant) {
                $participantPart = explode('@', $participant)[0];
                $senderPhone = explode(':', $participantPart)[0];
            }
            $senderName = $pushName;
            
            if ($isFromMe) {
                $senderName = 'Tú';
                $senderPhone = $channel->phone_number;
            }
            
            $groupJid = $remoteJid;
            $groupName = $data['groupName'] ?? null;
            
            $contact = Contact::where('channel_id', $channel->id)
                ->where('is_group', true)
                ->where('group_jid', $groupJid)
                ->first();
            
            if (!$contact) {
                $groupId = explode('@', $groupJid)[0];
                
                if (!$groupName) {
                    try {
                        $evolutionApi = app(EvolutionApiService::class);
                        $groupInfo = $evolutionApi->findGroupInfo($channel->instance_name, $groupJid);
                        if ($groupInfo['success'] && !empty($groupInfo['data'])) {
                            $groupName = $groupInfo['data']['subject'] ?? null;
                        }
                    } catch (\Exception $e) {
                        Log::debug('Could not fetch group info', ['groupJid' => $groupJid, 'error' => $e->getMessage()]);
                    }
                }
                
                $contact = Contact::create([
                    'channel_id' => $channel->id,
                    'phone_number' => $groupId,
                    'remote_jid' => $groupJid,
                    'is_group' => true,
                    'group_jid' => $groupJid,
                    'push_name' => $groupName,
                    'name' => $groupName,
                    'labels' => [],
                    'metadata' => [],
                ]);
            } else {
                if ($groupName && $groupName !== $contact->name) {
                    $contact->update(['name' => $groupName, 'push_name' => $groupName]);
                }
            }
        } else {
            // ⭐ INDIVIDUAL MESSAGE HANDLING — Usar LidResolverService
            $lidResolver = app(\App\Services\LidResolverService::class);
            $resolution = $lidResolver->resolve($webhookData);
            
            $phoneNumber = $resolution->phoneNumber;
            $isLidLead = $resolution->isUnresolvedLid();
            $lidPart = $resolution->lidIdentifier;
            
            // Limpiar pushName
            $safePushName = null;
            if ($pushName && !in_array(strtolower(trim($pushName)), ['você', 'voce'])) {
                $safePushName = $pushName;
            }
            
            // Si resolvimos el número, buscar y fusionar contacto LID previo
            if (!$isLidLead && $lidPart) {
                $existingLidContact = Contact::where('channel_id', $channel->id)
                    ->where('is_lid', true)
                    ->where(function ($q) use ($lidPart) {
                        $q->where('lid_jid', $lidPart)
                          ->orWhere('phone_number', $lidPart);
                    })
                    ->first();
                
                if ($existingLidContact) {
                    Log::info('Auto-fusionando contacto LID con número real', [
                        'lid_contact_id' => $existingLidContact->id,
                        'lid' => $lidPart,
                        'real_phone' => $phoneNumber,
                    ]);
                    $contact = $existingLidContact->resolveLid($phoneNumber, $channel->id);
                    goto messageProcessing;
                }
            }
            
            // Determinar JID para almacenar
            if ($isLidLead) {
                // LID sin resolver: descartar silenciosamente
                // No crear contactos con LID — el cliente no quiere verlos
                Log::debug('Mensaje de LID sin resolver descartado', [
                    'remoteJid' => $remoteJid,
                    'lid' => $lidPart,
                    'pushName' => $pushName,
                ]);
                return null;
            } else {
                $contactPhone = $phoneNumber;
                $contactJid = $phoneNumber . '@s.whatsapp.net';
            }
            
            // Buscar contacto existente
            $contact = Contact::where('channel_id', $channel->id)
                ->where('phone_number', $contactPhone)
                ->first();

            if (!$contact) {
                $contact = Contact::create([
                    'channel_id' => $channel->id,
                    'phone_number' => $contactPhone,
                    'remote_jid' => $contactJid,
                    'is_lid' => false,
                    'lid_jid' => null,
                    'push_name' => $safePushName,
                    'name' => $safePushName,
                    'labels' => [],
                    'metadata' => [],
                ]);
            } else {
                $updates = [];
                
                // Si el contacto era LID y ahora tenemos número real, resolver
                if ($contact->is_lid && !$isLidLead) {
                    $updates['is_lid'] = false;
                    $updates['phone_number'] = $phoneNumber;
                    $updates['remote_jid'] = $contactJid;
                    if ($contact->lid_jid) {
                        \App\Models\LidMapping::createMapping($contact->lid_jid, $phoneNumber, $messageId, $channel->id);
                    }
                } elseif (!$contact->is_lid && $contact->remote_jid !== $contactJid) {
                    $updates['remote_jid'] = $contactJid;
                }
                
                if ($safePushName && $contact->push_name !== $safePushName) {
                    $updates['push_name'] = $safePushName;
                    if (!$contact->name) {
                        $updates['name'] = $safePushName;
                    }
                }
                
                if (!empty($updates)) {
                    $contact->update($updates);
                }
            }
        }
        
        messageProcessing:
        // Normalize message data (unwrap viewOnce messages, etc.)
        $normalizedMessage = $this->normalizeMessageData($data['message'] ?? []);
        
        // Determine message type and content
        $messageType = $this->determineMessageType($normalizedMessage);
        $content = $this->extractMessageContent($normalizedMessage, $messageType);
        $mediaUrl = $this->extractMediaUrl($normalizedMessage, $messageType);
        $mediaMimeType = $this->extractMediaMimeType($normalizedMessage, $messageType);
        
        // For media messages, check for inline base64 first (instant), otherwise dispatch background job
        $shouldDispatchMediaJob = false;
        if (in_array($messageType, ['image', 'video', 'audio', 'document', 'sticker']) && $messageId) {
            // Evolution API v2 puede enviar el base64 en diferentes ubicaciones:
            // 1. $data['message']['base64'] — nivel raíz del message
            // 2. $data['message']['imageMessage']['base64'] — dentro del tipo específico
            // 3. $data['message']['base64'] después de normalizar viewOnce
            $inlineBase64 = $data['message']['base64'] ?? null;
            
            // Buscar en el tipo específico si no está en la raíz
            if (!$inlineBase64) {
                $typeKey = match ($messageType) {
                    'image' => 'imageMessage',
                    'video' => 'videoMessage',
                    'audio' => 'audioMessage',
                    'document' => 'documentMessage',
                    'sticker' => 'stickerMessage',
                    default => null,
                };
                if ($typeKey) {
                    $inlineBase64 = $data['message'][$typeKey]['base64'] ?? null;
                }
                // También buscar en documentWithCaptionMessage
                if (!$inlineBase64 && $messageType === 'document') {
                    $inlineBase64 = $data['message']['documentWithCaptionMessage']['message']['documentMessage']['base64'] ?? null;
                }
                // Buscar en pttMessage para audios PTT
                if (!$inlineBase64 && $messageType === 'audio') {
                    $inlineBase64 = $data['message']['pttMessage']['base64'] ?? null;
                }
            }
            
            if ($inlineBase64) {
                // Inline base64 available - save immediately (fast, no HTTP call)
                $localMediaUrl = $this->saveBase64Media($inlineBase64, $messageType, $messageId, $mediaMimeType);
                if ($localMediaUrl) {
                    $mediaUrl = $localMediaUrl;
                }
            } else {
                // No inline base64 - will dispatch background job after message is created
                $shouldDispatchMediaJob = true;
            }
        }
        
        // Check for duplicate message
        $existingMessage = Message::where('message_id', $messageId)
            ->where('channel_id', $channel->id)
            ->first();
            
        if ($existingMessage) {
            return $existingMessage;
        }
        
        // Parse timestamp
        $sentAt = now();
        if (isset($data['messageTimestamp'])) {
            $sentAt = \Carbon\Carbon::createFromTimestamp(
                $data['messageTimestamp'], 
                'UTC'
            )->setTimezone(config('app.timezone'));
        }

        // Determine direction for group messages
        $direction = ($isGroupMessage && $isFromMe) ? 'outgoing' : ($isGroupMessage ? 'incoming' : 'incoming');

        // Create message
        $message = Message::create([
            'contact_id' => $contact->id,
            'channel_id' => $channel->id,
            'message_id' => $messageId,
            'direction' => $direction,
            'type' => $messageType,
            'content' => $content,
            'media_url' => $mediaUrl,
            'media_mime_type' => $mediaMimeType,
            'sender_name' => $senderName,
            'sender_phone' => $senderPhone,
            'status' => 'delivered',
            'is_read' => $isFromMe,
            'metadata' => $data,
            'sent_at' => $sentAt,
        ]);

        // Dispatch background job to download media if needed
        if ($shouldDispatchMediaJob && $messageId) {
            DownloadMediaJob::dispatch(
                messageId: $message->id,
                instanceName: $instanceName,
                externalMessageId: $messageId,
                remoteJid: $remoteJid,
                type: $messageType,
                mimeType: $mediaMimeType,
            );
        }
        
        // ⭐ AUTO-RESOLVE LID: Si el contacto es LID y envía un contacto VCard,
        // intentar extraer el número real para desenmascarar el lead.
        if ($contact->is_lid && !$contact->isResolvedLid() && $messageType === 'contact') {
            $this->tryResolveLidFromVCard($contact, $data['message'] ?? []);
        }
        
        return $message;
    }

    /**
     * Save inline base64 media directly (fast, no HTTP call).
     * Used when Evolution API includes base64 in the webhook payload.
     */
    private function saveBase64Media(string $base64, string $type, string $messageId, ?string $mimeType): ?string
    {
        try {
            $resolvedMimeType = $mimeType ?? 'application/octet-stream';
            $extension = $this->getExtensionFromMimeType($resolvedMimeType, $type);
            $filename = $type . '_' . $messageId . '.' . $extension;
            $path = 'chat-media/' . date('Y/m') . '/' . $filename;

            $content = base64_decode($base64);
            if ($content === false || strlen($content) === 0) {
                return null;
            }

            \Illuminate\Support\Facades\Storage::disk('public')->put($path, $content);
            return \Illuminate\Support\Facades\Storage::disk('public')->url($path);
        } catch (\Exception $e) {
            Log::warning('Failed to save inline base64 media', [
                'messageId' => $messageId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Intentar resolver un contacto LID a partir de una VCard enviada.
     * Extrae números de teléfono del formato VCard de WhatsApp.
     */
    private function tryResolveLidFromVCard(Contact $contact, array $messageData): void
    {
        try {
            $vcard = $messageData['contactMessage']['vcard']
                ?? $messageData['contactsArrayMessage']['contacts'][0]['vcard']
                ?? null;

            if (!$vcard) {
                return;
            }

            // Extraer números de teléfono del VCard (formato TEL:+573XXXXXXXXX o TEL;...)
            preg_match_all('/TEL[^:]*:[\s]*\+?([0-9\s\-]+)/i', $vcard, $matches);

            if (empty($matches[1])) {
                return;
            }

            foreach ($matches[1] as $rawNumber) {
                $cleanNumber = preg_replace('/[^0-9]/', '', $rawNumber);

                // Validar que sea un número real (mínimo 10 dígitos)
                if (strlen($cleanNumber) >= 10 && strlen($cleanNumber) <= 15) {
                    Log::info('LID resuelto via VCard', [
                        'contact_id' => $contact->id,
                        'lid' => $contact->lid_jid,
                        'resolved_phone' => $cleanNumber,
                    ]);

                    $contact->resolveLid($cleanNumber);
                    return;
                }
            }
        } catch (\Exception $e) {
            Log::warning('Error al intentar resolver LID desde VCard', [
                'contact_id' => $contact->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Normalize message data by unwrapping viewOnce messages.
     * This ensures all extract methods can work with a flat structure.
     */
    private function normalizeMessageData(array $messageData): array
    {
        // Unwrap viewOnceMessage
        if (isset($messageData['viewOnceMessage']['message'])) {
            $inner = $messageData['viewOnceMessage']['message'];
            $inner['_isViewOnce'] = true;
            return $inner;
        }
        // Unwrap viewOnceMessageV2
        if (isset($messageData['viewOnceMessageV2']['message'])) {
            $inner = $messageData['viewOnceMessageV2']['message'];
            $inner['_isViewOnce'] = true;
            return $inner;
        }
        return $messageData;
    }

    /**
     * Get file extension from MIME type.
     */
    private function getExtensionFromMimeType(string $mimeType, string $type): string
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'video/mp4' => 'mp4',
            'video/3gpp' => '3gp',
            'audio/ogg' => 'ogg',
            'audio/mpeg' => 'mp3',
            'audio/mp4' => 'm4a',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        ];
        
        if (isset($map[$mimeType])) {
            return $map[$mimeType];
        }
        
        // Default extensions by type
        return match ($type) {
            'image' => 'jpg',
            'video' => 'mp4',
            'audio' => 'ogg',
            'document' => 'bin',
            default => 'bin',
        };
    }

    /**
     * Get conversation messages with pagination.
     * Requirements: 3.4
     */
    public function getConversationMessages(
        int $contactId, 
        int $channelId, 
        int $limit = 50, 
        ?int $beforeId = null
    ): Collection {
        $query = Message::where('contact_id', $contactId)
            ->where('channel_id', $channelId);
        
        if ($beforeId !== null) {
            $query->where('id', '<', $beforeId);
        }
        
        return $query->orderBy('sent_at', 'asc')
            ->orderBy('id', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Mark all incoming messages in a conversation as read.
     * Requirements: 2.5
     */
    public function markMessagesAsRead(int $contactId, int $channelId): void
    {
        Message::where('contact_id', $contactId)
            ->where('channel_id', $channelId)
            ->where('direction', 'incoming')
            ->where('is_read', false)
            ->update(['is_read' => true]);
    }


    /**
     * Normalize phone number to standard format.
     */
    private function normalizePhoneNumber(string $phoneNumber): string
    {
        // Remove all non-numeric characters except leading +
        $normalized = preg_replace('/[^0-9+]/', '', $phoneNumber);
        
        // Remove leading + if present
        $normalized = ltrim($normalized, '+');
        
        return $normalized;
    }

    /**
     * Extract phone number from WhatsApp JID.
     */
    private function extractPhoneFromJid(string $jid): string
    {
        // JID format: 573001234567@s.whatsapp.net
        return explode('@', $jid)[0] ?? $jid;
    }

    /**
     * Determine message type from webhook data.
     */
    private function determineMessageType(array $messageData): string
    {
        if (isset($messageData['conversation']) || isset($messageData['extendedTextMessage'])) {
            return 'text';
        }
        if (isset($messageData['imageMessage'])) {
            return 'image';
        }
        if (isset($messageData['documentMessage']) || isset($messageData['documentWithCaptionMessage'])) {
            return 'document';
        }
        if (isset($messageData['audioMessage']) || isset($messageData['pttMessage'])) {
            return 'audio';
        }
        if (isset($messageData['videoMessage'])) {
            return 'video';
        }
        if (isset($messageData['contactMessage']) || isset($messageData['contactsArrayMessage'])) {
            return 'contact';
        }
        if (isset($messageData['locationMessage']) || isset($messageData['liveLocationMessage'])) {
            return 'location';
        }
        if (isset($messageData['stickerMessage'])) {
            return 'sticker';
        }
        if (isset($messageData['protocolMessage'])) {
            $protoType = $messageData['protocolMessage']['type'] ?? null;
            if ($protoType === 'REVOKE' || $protoType === 0) {
                return 'deleted';
            }
        }
        // Llamadas de WhatsApp
        if (isset($messageData['bcallMessage']) || isset($messageData['callMessage'])) {
            return 'call';
        }
        // viewOnceMessage is handled by normalizeMessageData() before this method is called
        if (isset($messageData['pollCreationMessage']) || isset($messageData['pollCreationMessageV3'])) {
            return 'text';
        }
        // Encuestas (respuestas)
        if (isset($messageData['pollUpdateMessage'])) {
            return 'text';
        }
        
        return 'other';
    }

    /**
     * Extract message content based on type.
     */
    private function extractMessageContent(array $messageData, string $type): ?string
    {
        return match ($type) {
            'text' => $messageData['conversation'] 
                ?? $messageData['extendedTextMessage']['text'] 
                ?? ($messageData['pollCreationMessage']['name'] ?? null ? '📊 ' . $messageData['pollCreationMessage']['name'] : null)
                ?? ($messageData['pollCreationMessageV3']['name'] ?? null ? '📊 ' . $messageData['pollCreationMessageV3']['name'] : null)
                ?? null,
            'image' => $messageData['imageMessage']['caption'] 
                ?? (($messageData['_isViewOnce'] ?? false) ? '[Vista única]' : null)
                ?? null,
            'document' => $messageData['documentMessage']['fileName'] 
                ?? $messageData['documentWithCaptionMessage']['message']['documentMessage']['fileName'] 
                ?? '[Documento]',
            'audio' => '[Audio]',
            'video' => $messageData['videoMessage']['caption'] 
                ?? (($messageData['_isViewOnce'] ?? false) ? '[Vista única]' : null)
                ?? null,
            'contact' => $messageData['contactMessage']['displayName'] 
                ?? $messageData['contactsArrayMessage']['contacts'][0]['displayName'] 
                ?? '[Contacto]',
            'location' => $messageData['locationMessage']['name'] 
                ?? $messageData['locationMessage']['address'] 
                ?? $messageData['liveLocationMessage']['caption']
                ?? '[Ubicación]',
            'sticker' => '[Sticker]',
            'deleted' => '[Mensaje eliminado]',
            'call' => '📞 Llamada',
            'other' => '[Mensaje no soportado]',
            default => null,
        };
    }

    /**
     * Extract media URL based on type.
     */
    private function extractMediaUrl(array $messageData, string $type): ?string
    {
        return match ($type) {
            'image' => $messageData['imageMessage']['url'] ?? null,
            'document' => $messageData['documentMessage']['url'] 
                ?? $messageData['documentWithCaptionMessage']['message']['documentMessage']['url'] 
                ?? null,
            'audio' => $messageData['audioMessage']['url'] ?? $messageData['pttMessage']['url'] ?? null,
            'video' => $messageData['videoMessage']['url'] ?? null,
            'sticker' => $messageData['stickerMessage']['url'] ?? null,
            default => null,
        };
    }

    /**
     * Extract media MIME type based on type.
     */
    private function extractMediaMimeType(array $messageData, string $type): ?string
    {
        return match ($type) {
            'image' => $messageData['imageMessage']['mimetype'] ?? 'image/jpeg',
            'document' => $messageData['documentMessage']['mimetype'] 
                ?? $messageData['documentWithCaptionMessage']['message']['documentMessage']['mimetype'] 
                ?? 'application/octet-stream',
            'audio' => $messageData['audioMessage']['mimetype'] ?? $messageData['pttMessage']['mimetype'] ?? 'audio/ogg; codecs=opus',
            'video' => $messageData['videoMessage']['mimetype'] ?? 'video/mp4',
            'sticker' => $messageData['stickerMessage']['mimetype'] ?? 'image/webp',
            default => null,
        };
    }
}
