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
use Illuminate\Support\Facades\Storage;

/**
 * Job to download media from Evolution API and save locally.
 * 
 * This prevents the webhook from blocking while downloading/saving media files.
 * The message is created immediately with a placeholder, and media is downloaded
 * in the background.
 */
class DownloadMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;
    public array $backoff = [5, 15, 30];

    public function __construct(
        private int $messageId,
        private string $instanceName,
        private string $externalMessageId,
        private string $remoteJid,
        private string $type,
        private ?string $mimeType = null,
        private ?string $inlineBase64 = null,
    ) {}

    public function handle(EvolutionApiService $evolutionApi): void
    {
        $message = Message::find($this->messageId);
        
        if (!$message) {
            Log::warning('DownloadMediaJob: Message not found', ['message_id' => $this->messageId]);
            return;
        }

        // Skip if media was already downloaded
        if ($message->media_url && !str_contains($message->media_url, 'evolution') && Storage::disk('public')->exists(str_replace('/storage/', '', $message->media_url))) {
            return;
        }

        try {
            $base64 = null;
            $resolvedMimeType = $this->mimeType;

            // Option 1: Use inline base64 from webhook
            if ($this->inlineBase64) {
                $base64 = $this->inlineBase64;
            }

            // Option 2: Fetch from Evolution API
            if (!$base64 && $this->remoteJid) {
                $result = $evolutionApi->getMediaBase64($this->instanceName, $this->externalMessageId, $this->remoteJid);
                
                if ($result['success'] && !empty($result['data']['base64'])) {
                    $base64 = $result['data']['base64'];
                    $resolvedMimeType = $result['data']['mimetype'] ?? $this->mimeType;
                } else {
                    Log::warning('DownloadMediaJob: Failed to fetch media from API', [
                        'message_id' => $this->messageId,
                        'external_id' => $this->externalMessageId,
                        'error' => $result['error'] ?? 'No base64 data',
                    ]);
                    // Re-throw to trigger retry
                    throw new \RuntimeException('Failed to download media from Evolution API');
                }
            }

            if (!$base64) {
                Log::error('DownloadMediaJob: No base64 data available', [
                    'message_id' => $this->messageId,
                ]);
                return;
            }

            $resolvedMimeType = $resolvedMimeType ?? 'application/octet-stream';
            $extension = $this->getExtensionFromMimeType($resolvedMimeType);
            $filename = $this->type . '_' . $this->externalMessageId . '.' . $extension;
            $path = 'chat-media/' . date('Y/m') . '/' . $filename;

            $content = base64_decode($base64);
            
            if ($content === false || strlen($content) === 0) {
                Log::error('DownloadMediaJob: Failed to decode base64', [
                    'message_id' => $this->messageId,
                ]);
                return;
            }

            Storage::disk('public')->put($path, $content);
            $mediaUrl = Storage::disk('public')->url($path);

            $message->update([
                'media_url' => $mediaUrl,
                'media_mime_type' => $resolvedMimeType,
            ]);

            Log::debug('DownloadMediaJob: Media saved', [
                'message_id' => $this->messageId,
                'path' => $path,
                'size' => strlen($content),
            ]);
        } catch (\RuntimeException $e) {
            // Let it retry
            throw $e;
        } catch (\Exception $e) {
            Log::error('DownloadMediaJob: Exception', [
                'message_id' => $this->messageId,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    private function getExtensionFromMimeType(string $mimeType): string
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

        return $map[$mimeType] ?? match ($this->type) {
            'image' => 'jpg',
            'video' => 'mp4',
            'audio' => 'ogg',
            'document' => 'bin',
            'sticker' => 'webp',
            default => 'bin',
        };
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('DownloadMediaJob: All retries failed', [
            'message_id' => $this->messageId,
            'external_id' => $this->externalMessageId,
            'exception' => $exception->getMessage(),
        ]);
    }
}
