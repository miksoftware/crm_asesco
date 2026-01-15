<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Models\Contact;
use App\Models\Message;
use App\Services\EvolutionApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ImportChatsFromEvolution extends Command
{
    protected $signature = 'chats:import 
                            {--channel= : Specific channel ID to import from}
                            {--limit=500 : Max messages to import per channel}';
    
    protected $description = 'Import existing chats and messages from Evolution API';

    public function handle(EvolutionApiService $evolutionApi): int
    {
        $this->info('Importando chats desde Evolution API...');

        $query = Channel::where('is_active', true)->where('status', 'connected');
        
        if ($channelId = $this->option('channel')) {
            $query->where('id', $channelId);
        }

        $channels = $query->get();

        if ($channels->isEmpty()) {
            $this->warn('No se encontraron canales conectados.');
            return Command::SUCCESS;
        }

        $limit = (int) $this->option('limit');

        foreach ($channels as $channel) {
            $this->newLine();
            $this->info("Canal: {$channel->name} ({$channel->instance_name})");
            $this->importMessagesForChannel($channel, $evolutionApi, $limit);
        }

        $this->newLine();
        $this->info('¡Importación completada!');
        return Command::SUCCESS;
    }

    private function importMessagesForChannel(Channel $channel, EvolutionApiService $evolutionApi, int $limit): void
    {
        $page = 1;
        $perPage = 100;
        $imported = 0;
        $skipped = 0;

        $this->line("  Obteniendo mensajes...");

        while ($imported < $limit) {
            $result = $evolutionApi->fetchAllMessages($channel->instance_name, $page, $perPage);

            if (!$result['success']) {
                $this->error("  Error: " . ($result['error'] ?? 'Unknown'));
                break;
            }

            $messagesData = $result['data']['messages'] ?? $result['data'] ?? [];
            $records = $messagesData['records'] ?? $messagesData ?? [];

            if (empty($records)) {
                $this->line("  No hay más mensajes.");
                break;
            }

            foreach ($records as $msgData) {
                if ($imported >= $limit) {
                    break;
                }

                $result = $this->processMessage($channel, $msgData);
                if ($result === 'imported') {
                    $imported++;
                } elseif ($result === 'skipped') {
                    $skipped++;
                }
            }

            $total = $messagesData['total'] ?? count($records);
            $this->line("  Página {$page}: {$imported} importados, {$skipped} omitidos (Total en API: {$total})");

            $page++;

            // Safety check
            if ($page > 100) {
                break;
            }
        }

        $this->info("  ✓ Importación finalizada: {$imported} mensajes importados, {$skipped} omitidos");
    }

    private function processMessage(Channel $channel, array $msgData): string
    {
        $key = $msgData['key'] ?? [];
        $messageId = $key['id'] ?? null;
        $remoteJid = $key['remoteJid'] ?? null;

        if (!$messageId || !$remoteJid) {
            return 'invalid';
        }

        // Skip groups
        if (str_contains($remoteJid, '@g.us')) {
            return 'skipped';
        }

        // Check if message already exists
        if (Message::where('message_id', $messageId)->exists()) {
            return 'skipped';
        }

        // Extract phone number from JID
        $phoneNumber = $this->extractPhoneNumber($remoteJid);
        if (!$phoneNumber) {
            return 'invalid';
        }

        // Create or update contact
        $contact = Contact::updateOrCreate(
            [
                'channel_id' => $channel->id,
                'phone_number' => $phoneNumber,
            ],
            [
                'remote_jid' => $remoteJid,
                'push_name' => $msgData['pushName'] ?? null,
            ]
        );

        // Extract message content
        $messageContent = $msgData['message'] ?? [];
        $content = $messageContent['conversation'] 
            ?? $messageContent['extendedTextMessage']['text'] ?? null
            ?? $messageContent['imageMessage']['caption'] ?? null
            ?? $messageContent['videoMessage']['caption'] ?? null
            ?? $messageContent['documentMessage']['caption'] ?? null
            ?? $messageContent['documentMessage']['fileName'] ?? null
            ?? '[Media]';

        // Determine message type (must match enum: text, image, document, audio, video)
        $messageType = $msgData['messageType'] ?? 'conversation';
        $type = match (true) {
            str_contains($messageType, 'image') => 'image',
            str_contains($messageType, 'video') => 'video',
            str_contains($messageType, 'audio'), str_contains($messageType, 'ptt') => 'audio',
            str_contains($messageType, 'document') => 'document',
            str_contains($messageType, 'sticker') => 'image', // Map sticker to image
            default => 'text',
        };

        if (str_contains($messageType, 'sticker')) {
            $content = '[Sticker]';
        }

        // Parse timestamp
        $timestamp = $msgData['messageTimestamp'] ?? null;
        $sentAt = $timestamp 
            ? Carbon::createFromTimestamp($timestamp) 
            : now();

        // Determine direction
        $isFromMe = $key['fromMe'] ?? false;
        $direction = $isFromMe ? 'outgoing' : 'incoming';

        // Status: for incoming messages use 'delivered', for outgoing use 'sent'
        $status = $isFromMe ? 'sent' : 'delivered';

        // Create message
        Message::create([
            'channel_id' => $channel->id,
            'contact_id' => $contact->id,
            'message_id' => $messageId,
            'content' => $content ?: '[Media]',
            'type' => $type,
            'direction' => $direction,
            'status' => $status,
            'sent_at' => $sentAt,
            'is_read' => true, // Mark imported messages as read
        ]);

        return 'imported';
    }

    private function extractPhoneNumber(string $remoteJid): ?string
    {
        // Format: 573001234567@s.whatsapp.net
        if (preg_match('/^(\d+)@/', $remoteJid, $matches)) {
            return $matches[1];
        }
        return null;
    }
}
