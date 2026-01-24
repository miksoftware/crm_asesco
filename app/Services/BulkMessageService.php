<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Channel;
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
                $recipient->update([
                    'status' => 'sent',
                    'message_id' => $response['data']['key']['id'] ?? null,
                    'sent_at' => now(),
                ]);
                
                Log::info('Bulk message sent', [
                    'campaign_id' => $campaign->id,
                    'recipient_id' => $recipient->id,
                    'phone' => $phoneNumber,
                ]);
                
                return true;
            } else {
                $errorMsg = $response['error'] ?? 'Error desconocido';
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
            $recipient->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
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
