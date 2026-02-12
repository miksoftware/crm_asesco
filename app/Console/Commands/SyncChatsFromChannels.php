<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Models\Contact;
use App\Models\Message;
use App\Services\EvolutionApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SyncChatsFromChannels extends Command
{
    protected $signature = 'chats:sync {--channel= : ID del canal específico} {--limit=100 : Límite de chats por canal} {--media : Descargar media de mensajes existentes sin media}';
    protected $description = 'Sincroniza chats desde Evolution API para todos los canales conectados';

    public function __construct(
        private EvolutionApiService $evolutionApi
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $channelId = $this->option('channel');
        $limit = (int) $this->option('limit');
        $downloadMedia = $this->option('media');

        $query = Channel::where('status', 'connected');
        
        if ($channelId) {
            $query->where('id', $channelId);
        }

        $channels = $query->get();

        if ($channels->isEmpty()) {
            $this->info('No hay canales conectados para sincronizar.');
            return Command::SUCCESS;
        }

        $this->info("Sincronizando {$channels->count()} canal(es)...");

        foreach ($channels as $channel) {
            if ($downloadMedia) {
                $this->downloadPendingMedia($channel);
            } else {
                $this->syncChannel($channel, $limit);
            }
        }

        $this->newLine();
        $this->info('¡Sincronización completada!');

        return Command::SUCCESS;
    }

    private function syncChannel(Channel $channel, int $limit): void
    {
        $this->info("→ Canal: {$channel->name}");

        try {
            $response = $this->evolutionApi->fetchChats($channel->instance_name);

            if (!$response['success']) {
                $this->warn("  Error obteniendo chats: " . ($response['error'] ?? 'Desconocido'));
                return;
            }

            $chats = $response['data'] ?? [];
            $chats = array_slice($chats, 0, $limit);

            $newContacts = 0;
            $newMessages = 0;

            foreach ($chats as $chat) {
                $remoteJid = $chat['id'] ?? $chat['remoteJid'] ?? null;
                
                if (!$remoteJid) {
                    continue;
                }

                $isGroup = str_ends_with($remoteJid, '@g.us');
                $isIndividual = str_ends_with($remoteJid, '@s.whatsapp.net');

                if (!$isGroup && !$isIndividual) {
                    continue;
                }

                if ($isGroup) {
                    // Group chat
                    $groupId = explode('@', $remoteJid)[0];
                    $groupName = $chat['name'] ?? $chat['pushName'] ?? $chat['subject'] ?? null;

                    $contact = Contact::where('channel_id', $channel->id)
                        ->where('is_group', true)
                        ->where('group_jid', $remoteJid)
                        ->first();

                    if (!$contact) {
                        $contact = Contact::create([
                            'channel_id' => $channel->id,
                            'phone_number' => $groupId,
                            'remote_jid' => $remoteJid,
                            'is_group' => true,
                            'group_jid' => $remoteJid,
                            'name' => $groupName,
                            'push_name' => $groupName,
                        ]);
                        $newContacts++;
                    } else {
                        if ($groupName && !$contact->name) {
                            $contact->update(['name' => $groupName, 'push_name' => $groupName]);
                        }
                    }
                } else {
                    // Individual chat
                    $phoneNumber = str_replace('@s.whatsapp.net', '', $remoteJid);
                    
                    if (!preg_match('/^[1-9]\d{9,14}$/', $phoneNumber)) {
                        continue;
                    }

                    $contact = Contact::firstOrCreate(
                        [
                            'channel_id' => $channel->id,
                            'phone_number' => $phoneNumber,
                        ],
                        [
                            'remote_jid' => $remoteJid,
                            'name' => $chat['name'] ?? null,
                            'push_name' => $chat['pushName'] ?? $chat['name'] ?? null,
                        ]
                    );

                    if ($contact->wasRecentlyCreated) {
                        $newContacts++;
                    } else {
                        if (!$contact->remote_jid) {
                            $contact->update(['remote_jid' => $remoteJid]);
                        }
                    }
                }

                $messagesResult = $this->syncMessages($channel, $contact, $remoteJid);
                $newMessages += $messagesResult;
            }

            $this->info("  ✓ {$newContacts} contactos nuevos, {$newMessages} mensajes nuevos");

        } catch (\Exception $e) {
            $this->error("  Error: " . $e->getMessage());
            Log::error('Chat sync error', [
                'channel' => $channel->name,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function syncMessages(Channel $channel, Contact $contact, string $remoteJid): int
    {
        try {
            $response = $this->evolutionApi->fetchMessages($channel->instance_name, $remoteJid, 20);

            if (!$response['success']) {
                return 0;
            }

            $messages = $response['data']['messages'] ?? $response['data'] ?? [];
            $newCount = 0;

            foreach ($messages as $msg) {
                $messageId = $msg['key']['id'] ?? null;
                
                if (!$messageId) {
                    continue;
                }

                if (Message::where('message_id', $messageId)->exists()) {
                    continue;
                }

                $fromMe = $msg['key']['fromMe'] ?? false;
                $messageContent = $msg['message'] ?? [];
                
                // Normalizar viewOnce
                $messageContent = $this->normalizeMessageData($messageContent);
                
                $type = $this->getMessageType($messageContent);
                $content = $this->extractMessageContent($messageContent, $type);
                $mediaMimeType = $this->extractMediaMimeType($messageContent, $type);
                
                // Parsear timestamp con timezone correcto
                $sentAt = now();
                if (isset($msg['messageTimestamp'])) {
                    $sentAt = \Carbon\Carbon::createFromTimestamp(
                        $msg['messageTimestamp'],
                        'UTC'
                    )->setTimezone(config('app.timezone'));
                }

                // Intentar descargar media inline (base64 del webhook)
                $mediaUrl = null;
                $inlineBase64 = $msg['message']['base64'] ?? null;
                if (in_array($type, ['image', 'video', 'audio', 'document', 'sticker']) && $messageId) {
                    $mediaUrl = $this->downloadAndSaveMedia(
                        $channel->instance_name, $messageId, $remoteJid, $type, $mediaMimeType, $inlineBase64
                    );
                }

                // Extract group sender info if this is a group message
                $senderName = null;
                $senderPhone = null;
                $isGroup = $contact->is_group ?? false;
                
                if ($isGroup) {
                    $participant = $msg['key']['participant'] ?? null;
                    if ($participant) {
                        $participantPart = explode('@', $participant)[0];
                        $senderPhone = explode(':', $participantPart)[0];
                    }
                    $senderName = $msg['pushName'] ?? null;
                    if ($fromMe) {
                        $senderName = 'Tú';
                        $senderPhone = $channel->phone_number;
                    }
                }

                Message::create([
                    'contact_id' => $contact->id,
                    'channel_id' => $channel->id,
                    'message_id' => $messageId,
                    'direction' => $fromMe ? 'outgoing' : 'incoming',
                    'type' => $type,
                    'content' => $content,
                    'media_url' => $mediaUrl,
                    'media_mime_type' => $mediaMimeType,
                    'sender_name' => $senderName,
                    'sender_phone' => $senderPhone,
                    'status' => $fromMe ? 'sent' : 'delivered',
                    'is_read' => $fromMe,
                    'sent_at' => $sentAt,
                ]);

                $newCount++;
            }

            if ($newCount > 0) {
                $contact->touch();
            }

            return $newCount;

        } catch (\Exception $e) {
            Log::warning('Message sync error', [
                'contact' => $contact->phone_number,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Descargar media pendiente de mensajes que no tienen media_url.
     */
    private function downloadPendingMedia(Channel $channel): void
    {
        $this->info("→ Descargando media pendiente: {$channel->name}");

        $messages = Message::where('channel_id', $channel->id)
            ->whereNull('media_url')
            ->whereIn('type', ['image', 'video', 'audio', 'document', 'sticker'])
            ->whereNotNull('message_id')
            ->limit(50)
            ->get();

        if ($messages->isEmpty()) {
            $this->info("  No hay media pendiente.");
            return;
        }

        $downloaded = 0;
        foreach ($messages as $message) {
            $contact = $message->contact;
            if (!$contact) continue;

            $remoteJid = $contact->remote_jid ?? ($contact->phone_number . '@s.whatsapp.net');
            
            $mediaUrl = $this->downloadAndSaveMedia(
                $channel->instance_name,
                $message->message_id,
                $remoteJid,
                $message->type,
                $message->media_mime_type
            );

            if ($mediaUrl) {
                $message->update(['media_url' => $mediaUrl]);
                $downloaded++;
            }
        }

        $this->info("  ✓ {$downloaded}/{$messages->count()} archivos descargados");
    }

    /**
     * Descargar media desde Evolution API y guardar localmente.
     */
    private function downloadAndSaveMedia(string $instanceName, string $messageId, string $remoteJid, string $type, ?string $mimeType, ?string $inlineBase64 = null): ?string
    {
        try {
            $base64 = null;
            $resolvedMimeType = $mimeType;

            if ($inlineBase64) {
                $base64 = $inlineBase64;
            }

            if (!$base64 && $remoteJid) {
                $result = $this->evolutionApi->getMediaBase64($instanceName, $messageId, $remoteJid);
                
                if ($result['success'] && !empty($result['data']['base64'])) {
                    $base64 = $result['data']['base64'];
                    $resolvedMimeType = $result['data']['mimetype'] ?? $mimeType;
                }
            }

            if (!$base64) {
                return null;
            }

            $resolvedMimeType = $resolvedMimeType ?? 'application/octet-stream';
            $extension = $this->getExtensionFromMimeType($resolvedMimeType, $type);
            $filename = $type . '_' . $messageId . '.' . $extension;
            $path = 'chat-media/' . date('Y/m') . '/' . $filename;

            $content = base64_decode($base64);
            if ($content === false || strlen($content) === 0) {
                return null;
            }

            Storage::disk('public')->put($path, $content);

            return Storage::disk('public')->url($path);
        } catch (\Exception $e) {
            Log::warning('Media download error in sync', [
                'messageId' => $messageId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Normalizar datos de mensaje (unwrap viewOnce).
     */
    private function normalizeMessageData(array $messageData): array
    {
        if (isset($messageData['viewOnceMessage']['message'])) {
            return $messageData['viewOnceMessage']['message'];
        }
        if (isset($messageData['viewOnceMessageV2']['message'])) {
            return $messageData['viewOnceMessageV2']['message'];
        }
        return $messageData;
    }

    private function extractMessageContent(array $messageData, string $type): ?string
    {
        return match ($type) {
            'text' => $messageData['conversation']
                ?? $messageData['extendedTextMessage']['text']
                ?? null,
            'image' => $messageData['imageMessage']['caption'] ?? null,
            'video' => $messageData['videoMessage']['caption'] ?? null,
            'document' => $messageData['documentMessage']['fileName']
                ?? $messageData['documentWithCaptionMessage']['message']['documentMessage']['fileName']
                ?? '[Documento]',
            'audio' => '[Audio]',
            'sticker' => '[Sticker]',
            'contact' => $messageData['contactMessage']['displayName']
                ?? $messageData['contactsArrayMessage']['contacts'][0]['displayName']
                ?? '[Contacto]',
            'location' => $messageData['locationMessage']['name']
                ?? $messageData['locationMessage']['address']
                ?? '[Ubicación]',
            'deleted' => '[Mensaje eliminado]',
            'other' => '[Mensaje no soportado]',
            default => null,
        };
    }

    private function extractMediaMimeType(array $messageData, string $type): ?string
    {
        return match ($type) {
            'image' => $messageData['imageMessage']['mimetype'] ?? 'image/jpeg',
            'video' => $messageData['videoMessage']['mimetype'] ?? 'video/mp4',
            'audio' => $messageData['audioMessage']['mimetype'] ?? $messageData['pttMessage']['mimetype'] ?? 'audio/ogg; codecs=opus',
            'document' => $messageData['documentMessage']['mimetype']
                ?? $messageData['documentWithCaptionMessage']['message']['documentMessage']['mimetype']
                ?? 'application/octet-stream',
            'sticker' => $messageData['stickerMessage']['mimetype'] ?? 'image/webp',
            default => null,
        };
    }

    private function getMessageType(array $messageData): string
    {
        if (isset($messageData['conversation']) || isset($messageData['extendedTextMessage'])) return 'text';
        if (isset($messageData['imageMessage'])) return 'image';
        if (isset($messageData['videoMessage'])) return 'video';
        if (isset($messageData['audioMessage']) || isset($messageData['pttMessage'])) return 'audio';
        if (isset($messageData['documentMessage']) || isset($messageData['documentWithCaptionMessage'])) return 'document';
        if (isset($messageData['stickerMessage'])) return 'sticker';
        if (isset($messageData['contactMessage']) || isset($messageData['contactsArrayMessage'])) return 'contact';
        if (isset($messageData['locationMessage']) || isset($messageData['liveLocationMessage'])) return 'location';
        if (isset($messageData['protocolMessage'])) {
            $protoType = $messageData['protocolMessage']['type'] ?? null;
            if ($protoType === 'REVOKE' || $protoType === 0) return 'deleted';
        }
        if (isset($messageData['pollCreationMessage']) || isset($messageData['pollCreationMessageV3'])) return 'text';

        return 'other';
    }

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
            'audio/ogg; codecs=opus' => 'ogg',
            'audio/mpeg' => 'mp3',
            'audio/mp4' => 'm4a',
            'application/pdf' => 'pdf',
        ];

        return $map[$mimeType] ?? match ($type) {
            'image' => 'jpg',
            'video' => 'mp4',
            'audio' => 'ogg',
            'document' => 'bin',
            default => 'bin',
        };
    }
}
