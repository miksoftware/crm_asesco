<?php

namespace App\Jobs;

use App\Events\CampaignProgressUpdated;
use App\Models\Campaign;
use App\Services\BulkMessageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 7200; // 2 horas máximo
    public int $tries = 1;

    public function __construct(
        public Campaign $campaign
    ) {}

    public function handle(BulkMessageService $bulkMessageService): void
    {
        $campaign = $this->campaign->fresh();
        
        if (!$campaign || !in_array($campaign->status, ['pending', 'running'])) {
            Log::info('Campaign not ready to process', [
                'campaign_id' => $this->campaign->id,
                'status' => $campaign?->status,
            ]);
            return;
        }

        // Marcar como en ejecución
        $campaign->update([
            'status' => 'running',
            'started_at' => $campaign->started_at ?? now(),
        ]);

        Log::info('Starting campaign processing', [
            'campaign_id' => $campaign->id,
            'total_recipients' => $campaign->total_recipients,
        ]);

        $processedInBatch = 0;
        $totalProcessed = 0;

        // Obtener destinatarios pendientes
        $recipients = $campaign->recipients()
            ->where('status', 'pending')
            ->get();

        foreach ($recipients as $recipient) {
            // Verificar si la campaña fue pausada o cancelada
            $campaign->refresh();
            if (!in_array($campaign->status, ['running'])) {
                Log::info('Campaign stopped', [
                    'campaign_id' => $campaign->id,
                    'status' => $campaign->status,
                    'processed' => $totalProcessed,
                ]);
                return;
            }

            // Enviar mensaje
            $success = $bulkMessageService->sendToRecipient($campaign, $recipient);
            
            $totalProcessed++;
            $processedInBatch++;

            // Actualizar contadores
            $bulkMessageService->updateCampaignCounts($campaign);

            // Emitir evento de progreso
            event(new CampaignProgressUpdated($campaign->fresh()));

            // Delay entre mensajes (anti-ban)
            $delay = $bulkMessageService->getRandomDelay(
                $campaign->delay_min,
                $campaign->delay_max
            );
            
            // Pausa larga cada batch
            if ($processedInBatch >= $campaign->batch_size) {
                Log::info('Campaign batch pause', [
                    'campaign_id' => $campaign->id,
                    'processed' => $totalProcessed,
                    'pause_seconds' => $campaign->batch_pause,
                ]);
                
                sleep($campaign->batch_pause);
                $processedInBatch = 0;
            } else {
                sleep($delay);
            }
        }

        // Marcar como completada
        $campaign->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        // Actualizar contadores finales
        $bulkMessageService->updateCampaignCounts($campaign);

        // Emitir evento final
        event(new CampaignProgressUpdated($campaign->fresh()));

        Log::info('Campaign completed', [
            'campaign_id' => $campaign->id,
            'total_processed' => $totalProcessed,
            'sent' => $campaign->sent_count,
            'failed' => $campaign->failed_count,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Campaign job failed', [
            'campaign_id' => $this->campaign->id,
            'exception' => $exception->getMessage(),
        ]);

        $this->campaign->update([
            'status' => 'paused',
            'error_log' => $exception->getMessage(),
        ]);

        event(new CampaignProgressUpdated($this->campaign->fresh()));
    }
}
