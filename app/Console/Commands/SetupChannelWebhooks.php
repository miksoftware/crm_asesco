<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Services\EvolutionApiService;
use Illuminate\Console\Command;

class SetupChannelWebhooks extends Command
{
    protected $signature = 'channels:setup-webhooks 
                            {--channel= : Specific channel ID to setup}
                            {--url= : Custom webhook URL (overrides APP_URL)}';
    protected $description = 'Configure webhooks in Evolution API for all connected channels';

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
        
        $this->line("Webhook URL: {$webhookUrl}");
        $this->newLine();

        foreach ($channels as $channel) {
            $this->line("Canal: {$channel->name} ({$channel->instance_name})");

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
                $this->error("  ✗ Error: " . ($result['error'] ?? 'Unknown error'));
            }

            $this->newLine();
        }

        $this->info('¡Configuración completada!');
        return Command::SUCCESS;
    }
}
