<?php

namespace App\Console\Commands;

use App\Models\CampaignRecipient;
use Illuminate\Console\Command;

class FixCampaignErrorMessages extends Command
{
    protected $signature = 'campaigns:fix-errors';
    protected $description = 'Actualiza los mensajes de error de campañas a mensajes más amigables';

    public function handle(): int
    {
        $this->info('Actualizando mensajes de error...');

        $updated = 0;

        // Buscar todos los recipients con errores
        CampaignRecipient::where('status', 'failed')
            ->whereNotNull('error_message')
            ->chunkById(100, function ($recipients) use (&$updated) {
                foreach ($recipients as $recipient) {
                    $newMessage = $this->parseErrorMessage($recipient->error_message);
                    
                    if ($newMessage !== $recipient->error_message) {
                        $recipient->update(['error_message' => $newMessage]);
                        $updated++;
                    }
                }
            });

        $this->info("✓ Se actualizaron {$updated} mensajes de error");

        return Command::SUCCESS;
    }

    private function parseErrorMessage(string $rawError): string
    {
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
        if (str_contains($rawError, 'rate') || str_contains($rawError, 'limit')) {
            return 'Límite de envío alcanzado';
        }
        
        // Detectar errores de timeout
        if (str_contains($rawError, 'timeout') || str_contains($rawError, 'Timeout')) {
            return 'Tiempo de espera agotado';
        }
        
        // Detectar errores de bloqueo
        if (str_contains($rawError, 'blocked') || str_contains($rawError, 'spam')) {
            return 'Número bloqueado o marcado como spam';
        }
        
        // Detectar errores 400
        if (str_contains($rawError, '"status":400') || str_contains($rawError, 'Bad Request')) {
            return 'El número no está registrado en WhatsApp';
        }

        // Detectar errores de instancia
        if (str_contains($rawError, 'instance') || str_contains($rawError, 'Instance')) {
            return 'Error en la instancia de WhatsApp';
        }

        // Si el mensaje es muy largo, acortarlo
        if (strlen($rawError) > 100) {
            return 'Error al enviar mensaje';
        }
        
        return $rawError;
    }
}
