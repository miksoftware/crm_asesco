<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Services\EvolutionApiService;
use Illuminate\Console\Command;

class SetupChannelWebhooks extends Command
{
    protected $signature = 'channels:setup-webhooks 
                            {--channel= : Specific channel ID to setup}
                            {--url= : Custom webhook URL (overrides APP_URL)}
                            {--full : Configure settings + webhook + websocket (not just webhook)}';
    protected $description = 'Configure webhooks (and optionally settings + websocket) in Evolution API for all connected channels';

    public function handle(EvolutionApiService $evolutionApi): int
    {
        $this->info('Configurando webhooks en Evolution API...');

        $query = Channel::where('is_active', true);
        
        if ($channelId = $this->option('channel')) {
            $query->where('id', $channelId);
        }

        $channels = $query->get();

        if ($channels->isEmpty()) {
            $this->warn('No se encontraron canales activos.');
            return Command::SUCCESS;
        }

        // Use custom URL if provided, otherwise use APP_URL
        $baseUrl = $this->option('url') ?? config('app.url');
        $webhookUrl = rtrim($baseUrl, '/') . '/api/webhook/evolution';
        $fullConfig = $this->option('full');
        
        $this->line("Webhook URL: {$webhookUrl}");
        if ($fullConfig) {
            $this->line("Modo completo: settings + webhook + websocket");
        }
        $this->newLine();

        foreach ($channels as $channel) {
            $this->line("Canal: {$channel->name} ({$channel->instance_name})");

            // Configurar settings si es modo completo
            if ($fullConfig) {
                $settingsResult = $evolutionApi->setSettings($channel->instance_name);
                if ($settingsResult['success']) {
                    $this->info("  ✓ Settings configurados (syncFullHistory, etc.)");
                } else {
                    $this->error("  ✗ Error en settings: " . ($settingsResult['error'] ?? 'Error desconocido'));
                }
            }

            // Check current webhook config
            $currentWebhook = $evolutionApi->getWebhook($channel->instance_name);
            
            if ($currentWebhook['success']) {
                $existingUrl = $currentWebhook['data']['webhook']['url'] ?? $currentWebhook['data']['url'] ?? 'No configurado';
                $this->line("  Webhook actual: {$existingUrl}");
            }

            // Set webhook
            $result = $evolutionApi->setWebhook($channel->instance_name, $webhookUrl);

            if ($result['success']) {
                $this->info("  ✓ Webhook configurado correctamente");
            } else {
                $this->error("  ✗ Error webhook: " . ($result['error'] ?? 'Unknown error'));
            }

            // Configurar websocket si es modo completo
            if ($fullConfig) {
                $wsResult = $evolutionApi->setWebsocket($channel->instance_name);
                if ($wsResult['success']) {
                    $this->info("  ✓ WebSocket habilitado con eventos");
                } else {
                    $this->error("  ✗ Error websocket: " . ($wsResult['error'] ?? 'Error desconocido'));
                }
            }

            $this->newLine();
        }

        $this->info('¡Configuración completada!');
        return Command::SUCCESS;
    }
}
