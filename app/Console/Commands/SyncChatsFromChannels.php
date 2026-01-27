<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Models\Contact;
use App\Models\Message;
use App\Services\EvolutionApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncChatsFromChannels extends Command
{
    protected $signature = 'chats:sync {--channel= : ID del canal específico} {--limit=100 : Límite de chats por canal}';
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
            $this->syncChannel($channel, $limit);
        }

        $this->newLine();
        $this->info('¡Sincronización completada!');

        return Command::SUCCESS;
    }

    private function syncChannel(Channel $channel, int $limit): void
    {
        $this->info("→ Canal: {$channel->name}");

        try {
            // Obtener chats desde Evolution API
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
                
                if (!$remoteJid || !str_ends_with($remoteJid, '@s.whatsapp.net')) {
                    continue; // Solo chats individuales
                }

                // Extraer número de teléfono
                $phoneNumber = str_replace('@s.whatsapp.net', '', $remoteJid);
                
                // Buscar o crear contacto
                $contact = Contact::firstOrCreate(
                    [
                        'channel_id' => $channel->id,
                        'remote_jid' => $remoteJid,
                    ],
                    [
                        'phone_number' => $phoneNumber,
                        'name' => $chat['name'] ?? null,
                        'push_name' => $chat['pushName'] ?? $chat['name'] ?? null,
                    ]
                );

                if ($contact->wasRecentlyCreated) {
                    $newContacts++;
                }

                // Sincronizar mensajes recientes
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

                // Verificar si ya existe
                if (Message::where('message_id', $messageId)->exists()) {
                    continue;
                }

                $fromMe = $msg['key']['fromMe'] ?? false;
                $content = $this->extractMessageContent($msg);
                $messageType = $this->getMessageType($msg);
                $timestamp = isset($msg['messageTimestamp']) 
                    ? \Carbon\Carbon::createFromTimestamp($msg['messageTimestamp'])
                    : now();

                Message::create([
                    'contact_id' => $contact->id,
                    'message_id' => $messageId,
                    'from_me' => $fromMe,
                    'message_type' => $messageType,
                    'content' => $content,
                    'media_url' => $msg['message']['imageMessage']['url'] ?? $msg['message']['audioMessage']['url'] ?? null,
                    'status' => $fromMe ? 'sent' : 'received',
                    'sent_at' => $timestamp,
                    'created_at' => $timestamp,
                ]);

                $newCount++;
            }

            // Actualizar última actividad del contacto
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

    private function extractMessageContent(array $msg): ?string
    {
        $message = $msg['message'] ?? [];

        return $message['conversation'] 
            ?? $message['extendedTextMessage']['text'] 
            ?? $message['imageMessage']['caption'] 
            ?? $message['videoMessage']['caption']
            ?? $message['documentMessage']['fileName']
            ?? ($message['audioMessage'] ? '🎵 Audio' : null)
            ?? ($message['stickerMessage'] ? '🎨 Sticker' : null)
            ?? ($message['contactMessage'] ? '👤 Contacto' : null)
            ?? ($message['locationMessage'] ? '📍 Ubicación' : null)
            ?? null;
    }

    private function getMessageType(array $msg): string
    {
        $message = $msg['message'] ?? [];

        if (isset($message['imageMessage'])) return 'image';
        if (isset($message['videoMessage'])) return 'video';
        if (isset($message['audioMessage'])) return 'audio';
        if (isset($message['documentMessage'])) return 'document';
        if (isset($message['stickerMessage'])) return 'sticker';
        if (isset($message['contactMessage'])) return 'contact';
        if (isset($message['locationMessage'])) return 'location';

        return 'text';
    }
}
