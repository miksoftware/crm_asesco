<?php

namespace App\Jobs;

use App\Models\Message;
use App\Services\EvolutionApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job to send a message via Evolution API asynchronously.
 * 
 * This prevents the Livewire component from blocking while waiting
 * for the Evolution API HTTP response (~2-5 seconds).
 * The message is created with 'pending' status and updated to 'sent' or 'failed'.
 */
class SendMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 30;

    public function __construct(
        private int $messageId,
        private string $instanceName,
        private string $recipient,
        private string $text,
        private string $type = 'text',
        private ?string $mediaBase64 = null,
        private ?string $fileName = null,
        private ?string $mimeType = null,
        private ?string $caption = null,
    ) {}

    public function handle(EvolutionApiService $evolutionApi): void
    {
        $message = Message::find($this->messageId);
        
        if (!$message) {
            Log::warning('SendMessageJob: Message not found', ['message_id' => $this->messageId]);
            return;
        }

        // Skip if message was already processed (e.g., retry after success)
        if ($message->status === 'sent') {
            return;
        }

        try {
            $response = match ($this->type) {
                'text' => $evolutionApi->sendTextMessage($this->instanceName, $this->recipient, $this->text),
                'image' => $evolutionApi->sendImageMessage($this->instanceName, $this->recipient, $this->mediaBase64, $this->caption, $this->mimeType ?? 'image/jpeg'),
                'video' => $evolutionApi->sendVideoMessage($this->instanceName, $this->recipient, $this->mediaBase64, $this->caption, $this->mimeType ?? 'video/mp4'),
                'audio' => $evolutionApi->sendAudioMessage($this->instanceName, $this->recipient, $this->mediaBase64, $this->mimeType ?? 'audio/ogg; codecs=opus'),
                'document' => $evolutionApi->sendDocumentMessage($this->instanceName, $this->recipient, $this->mediaBase64, $this->fileName ?? 'file', $this->mimeType ?? 'application/octet-stream', $this->caption),
                default => $evolutionApi->sendTextMessage($this->instanceName, $this->recipient, $this->text),
            };

            if ($response['success']) {
                $externalId = $response['data']['key']['id'] ?? null;
                $message->update([
                    'message_id' => $externalId,
                    'status' => 'sent',
                ]);
            } else {
                $errorMsg = $response['error'] ?? 'Unknown Evolution API error';
                $message->update(['status' => 'failed']);
                Log::error('SendMessageJob: Evolution API failed', [
                    'message_id' => $this->messageId,
                    'recipient' => $this->recipient,
                    'error' => $errorMsg,
                ]);
            }
        } catch (\Exception $e) {
            $message->update(['status' => 'failed']);
            Log::error('SendMessageJob: Exception', [
                'message_id' => $this->messageId,
                'exception' => $e->getMessage(),
            ]);
            
            // Re-throw so the queue can retry
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $message = Message::find($this->messageId);
        if ($message && $message->status !== 'sent') {
            $message->update(['status' => 'failed']);
        }

        Log::error('SendMessageJob: All retries failed', [
            'message_id' => $this->messageId,
            'exception' => $exception->getMessage(),
        ]);
    }
}
