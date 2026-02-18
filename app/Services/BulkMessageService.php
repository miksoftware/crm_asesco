<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Message;
use Illuminate\Support\Facades\Log;

class BulkMessageService
{
    public function __construct(
        private EvolutionApiService $evolutionApi
    ) {}

    /**
     * Envía un mensaje a un destinatario de campaña.
     * Retorna true si se envió correctamente, false si falló.
     */
    public function sendToRecipient(Campaign $campaign, CampaignRecipient $recipient): bool
    {
        $channel = $campaign->channel;
        
        if (!$channel || $channel->status !== 'connected') {
            $recipient->update([
                'status' => 'failed',
                'error_message' => 'Canal no conectado',
            ]);
            return false;
        }

        // Preparar el mensaje con los placeholders
        $message = $this->renderMessage($campaign->message_content, [
            'nombre' => $recipient->name ?? '',
            'val1' => $recipient->val1 ?? '',
            'val2' => $recipient->val2 ?? '',
        ]);

        try {
            // Normalizar número de teléfono
            $phoneNumber = $this->normalizePhoneNumber($recipient->phone_number);
            
            // Verificar si el número es válido (opcional, puede desactivarse para velocidad)
            // $checkResult = $this->evolutionApi->checkWhatsAppNumber($channel->instance_name, $phoneNumber);
            // if (!$checkResult['exists']) {
            //     $recipient->update([
            //         'status' => 'invalid',
            //         'error_message' => 'Número no registrado en WhatsApp',
            //     ]);
            //     return false;
            // }

            // Enviar mensaje
            $response = $this->evolutionApi->sendTextMessage(
                $channel->instance_name,
                $phoneNumber,
                $message
            );

            if ($response['success']) {
                $messageId = $response['data']['key']['id'] ?? null;
                
                // Guardar mensaje en la base de datos local
                $this->saveMessageToDatabase($channel, $phoneNumber, $message, $messageId, $campaign->user_id);
                
                $recipient->update([
                    'status' => 'sent',
                    'message_id' => $messageId,
                    'sent_at' => now(),
                ]);
                
                Log::info('Bulk message sent', [
                    'campaign_id' => $campaign->id,
                    'recipient_id' => $recipient->id,
                    'phone' => $phoneNumber,
                ]);
                
                return true;
            } else {
                $errorMsg = $this->parseErrorMessage($response);
                $recipient->update([
                    'status' => 'failed',
                    'error_message' => $errorMsg,
                ]);
                
                Log::warning('Bulk message failed', [
                    'campaign_id' => $campaign->id,
                    'recipient_id' => $recipient->id,
                    'phone' => $phoneNumber,
                    'error' => $errorMsg,
                ]);
                
                return false;
            }
        } catch (\Exception $e) {
            $errorMsg = $this->parseExceptionMessage($e->getMessage());
            $recipient->update([
                'status' => 'failed',
                'error_message' => $errorMsg,
            ]);
            
            Log::error('Bulk message exception', [
                'campaign_id' => $campaign->id,
                'recipient_id' => $recipient->id,
                'exception' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * Parsea el mensaje de error de la API y lo convierte en un mensaje amigable.
     */
    private function parseErrorMessage(array $response): string
    {
        $rawError = $response['error'] ?? '';
        $statusCode = $response['status'] ?? 0;
        
        // Detectar si el número no existe en WhatsApp
        if (str_contains($rawError, '"exists":false') || str_contains($rawError, 'exists":false')) {
            return 'El número no está registrado en WhatsApp';
        }
        
        // Detectar errores de número inválido
        if (str_contains($rawError, 'invalid') || str_contains($rawError, 'Invalid')) {
            return 'Número de teléfono inválido';
        }
        
        // Detectar errores de conexión
        if (str_contains($rawError, 'not connected') || str_contains($rawError, 'disconnected')) {
            return 'Canal desconectado';
        }
        
        // Detectar errores de rate limit
        if (str_contains($rawError, 'rate') || str_contains($rawError, 'limit') || $statusCode === 429) {
            return 'Límite de envío alcanzado, intente más tarde';
        }
        
        // Detectar errores de timeout
        if (str_contains($rawError, 'timeout') || str_contains($rawError, 'Timeout')) {
            return 'Tiempo de espera agotado';
        }
        
        // Detectar errores de bloqueo
        if (str_contains($rawError, 'blocked') || str_contains($rawError, 'spam')) {
            return 'Número bloqueado o marcado como spam';
        }
        
        // Detectar errores de instancia
        if (str_contains($rawError, 'instance') || str_contains($rawError, 'Instance')) {
            return 'Error en la instancia de WhatsApp';
        }
        
        // Error genérico con código de estado
        if ($statusCode >= 400 && $statusCode < 500) {
            return 'Error en la solicitud (código ' . $statusCode . ')';
        }
        
        if ($statusCode >= 500) {
            return 'Error del servidor de WhatsApp';
        }
        
        // Si no se puede identificar, mostrar un mensaje genérico
        return $rawError ?: 'Error desconocido al enviar';
    }

    /**
     * Parsea excepciones y las convierte en mensajes amigables.
     */
    private function parseExceptionMessage(string $message): string
    {
        if (str_contains($message, 'Connection refused')) {
            return 'No se pudo conectar al servidor de WhatsApp';
        }
        
        if (str_contains($message, 'timed out') || str_contains($message, 'Timeout')) {
            return 'Tiempo de espera agotado';
        }
        
        if (str_contains($message, 'SSL') || str_contains($message, 'certificate')) {
            return 'Error de conexión segura';
        }
        
        // Limitar longitud del mensaje
        if (strlen($message) > 100) {
            return substr($message, 0, 100) . '...';
        }
        
        return $message ?: 'Error inesperado';
    }

    /**
     * Reemplaza los placeholders en el mensaje.
     */
    public function renderMessage(string $template, array $data): string
    {
        $message = $template;
        
        foreach ($data as $key => $value) {
            $message = str_replace('{' . $key . '}', $value ?? '', $message);
        }
        
        // Limpiar placeholders no reemplazados
        $message = preg_replace('/\{[a-zA-Z0-9_]+\}/', '', $message);
        
        return trim($message);
    }

    /**
     * Normaliza el número de teléfono.
     */
    private function normalizePhoneNumber(string $phoneNumber): string
    {
        // Eliminar todos los caracteres no numéricos
        $normalized = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        // Si no tiene código de país (menos de 12 dígitos para Colombia), agregar 57
        if (strlen($normalized) === 10) {
            $normalized = '57' . $normalized;
        }
        
        return $normalized;
    }

    /**
     * Guarda el mensaje enviado en la base de datos local.
     */
    private function saveMessageToDatabase(Channel $channel, string $phoneNumber, string $message, ?string $messageId, ?int $userId): void
    {
        try {
            $remoteJid = $phoneNumber . '@s.whatsapp.net';
            
            // Buscar o crear el contacto
            $contact = Contact::firstOrCreate(
                [
                    'channel_id' => $channel->id,
                    'remote_jid' => $remoteJid,
                ],
                [
                    'phone_number' => $phoneNumber,
                    'name' => null,
                    'push_name' => null,
                ]
            );

            // Crear el mensaje
            Message::create([
                'contact_id' => $contact->id,
                'channel_id' => $channel->id,
                'message_id' => $messageId ?? ('bulk_' . uniqid()),
                'direction' => 'outgoing',
                'type' => 'text',
                'content' => $message,
                'status' => 'sent',
                'is_read' => true,
                'user_id' => $userId,
                'sent_at' => now(),
            ]);

            // Actualizar última actividad del contacto
            $contact->touch();
            
        } catch (\Exception $e) {
            Log::warning('Failed to save bulk message to database', [
                'phone' => $phoneNumber,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Calcula el delay aleatorio entre mensajes.
     */
    public function getRandomDelay(int $min, int $max): int
    {
        return rand($min, $max);
    }

    /**
     * Actualiza los contadores de la campaña.
     */
    public function updateCampaignCounts(Campaign $campaign): void
    {
        $campaign->update([
            'sent_count' => $campaign->recipients()->where('status', 'sent')->count(),
            'failed_count' => $campaign->recipients()->whereIn('status', ['failed', 'invalid'])->count(),
            'pending_count' => $campaign->recipients()->where('status', 'pending')->count(),
        ]);
    }

    /**
     * Parsea un archivo CSV y retorna los destinatarios.
     */
    public function parseCsvFile(string $filePath): array
    {
        $recipients = [];
        
        if (($handle = fopen($filePath, 'r')) !== false) {
            $header = fgetcsv($handle, 1000, ',');
            
            // Normalizar headers
            $header = array_map(function ($h) {
                return strtolower(trim($h));
            }, $header);
            
            while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                if (count($row) < 1) continue;
                
                $data = array_combine($header, array_pad($row, count($header), ''));
                
                // Buscar columna de teléfono
                $phone = $data['telefono'] ?? $data['phone'] ?? $data['numero'] ?? $data['celular'] ?? null;
                
                if (!$phone) continue;
                
                $recipients[] = [
                    'phone_number' => $phone,
                    'name' => $data['nombre'] ?? $data['name'] ?? null,
                    'val1' => $data['val1'] ?? $data['variable1'] ?? null,
                    'val2' => $data['val2'] ?? $data['variable2'] ?? null,
                ];
            }
            
            fclose($handle);
        }
        
        return $recipients;
    }
}
