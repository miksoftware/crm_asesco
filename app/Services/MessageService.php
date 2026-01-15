<?php

namespace App\Services;

use App\Models\Channel;
use App\Models\Contact;
use App\Models\Message;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MessageService
{
    public function __construct(
        private EvolutionApiService $evolutionApi
    ) {}

    /**
     * Send a text message via Evolution API.
     * Requirements: 4.1, 4.2
     */
    public function sendTextMessage(int $channelId, string $phoneNumber, string $text): Message
    {
        $channel = Channel::findOrFail($channelId);
        
        // Verify channel is connected
        if ($channel->status !== 'connected') {
            throw new \Exception("El canal '{$channel->name}' no está conectado. Estado actual: {$channel->status}");
        }
        
        // Find or create contact
        $contact = Contact::firstOrCreate(
            [
                'channel_id' => $channelId,
                'phone_number' => $this->normalizePhoneNumber($phoneNumber),
            ],
            [
                'name' => null,
                'push_name' => null,
                'labels' => [],
                'metadata' => [],
            ]
        );

        // Create message with pending status
        $message = Message::create([
            'contact_id' => $contact->id,
            'channel_id' => $channelId,
            'direction' => 'outgoing',
            'type' => 'text',
            'content' => $text,
            'status' => 'pending',
            'is_read' => true,
            'sent_at' => now(),
        ]);

        try {
            // Send via Evolution API
            $response = $this->evolutionApi->sendTextMessage(
                $channel->instance_name,
                $phoneNumber,
                $text
            );

            Log::info('Evolution API sendTextMessage response', [
                'channel' => $channel->instance_name,
                'phone' => $phoneNumber,
                'response' => $response,
            ]);

            if ($response['success']) {
                $messageId = $response['data']['key']['id'] ?? null;
                $message->update([
                    'message_id' => $messageId,
                    'status' => 'sent',
                ]);
            } else {
                $errorMsg = $response['error'] ?? 'Error desconocido de Evolution API';
                $message->update(['status' => 'failed']);
                Log::error('Failed to send message via Evolution API', [
                    'channel_id' => $channelId,
                    'phone_number' => $phoneNumber,
                    'error' => $errorMsg,
                    'full_response' => $response,
                ]);
                throw new \Exception($errorMsg);
            }
        } catch (\Exception $e) {
            $message->update(['status' => 'failed']);
            Log::error('Exception sending message via Evolution API', [
                'channel_id' => $channelId,
                'phone_number' => $phoneNumber,
                'exception' => $e->getMessage(),
            ]);
            throw $e;
        }

        return $message->fresh();
    }


    /**
     * Process an incoming message from Evolution API webhook.
     * Requirements: 9.1
     */
    public function processIncomingMessage(array $webhookData): Message
    {
        $instanceName = $webhookData['instance'] ?? null;
        $data = $webhookData['data'] ?? [];
        
        // Find channel by instance name
        $channel = Channel::where('instance_name', $instanceName)->firstOrFail();
        
        // Extract message data
        $remoteJid = $data['key']['remoteJid'] ?? '';
        $phoneNumber = $this->extractPhoneFromJid($remoteJid);
        $messageId = $data['key']['id'] ?? null;
        $pushName = $data['pushName'] ?? null;
        
        // Determine message type and content
        $messageType = $this->determineMessageType($data['message'] ?? []);
        $content = $this->extractMessageContent($data['message'] ?? [], $messageType);
        $mediaUrl = $this->extractMediaUrl($data['message'] ?? [], $messageType);
        $mediaMimeType = $this->extractMediaMimeType($data['message'] ?? [], $messageType);
        
        // Find or create contact
        $contact = Contact::firstOrCreate(
            [
                'channel_id' => $channel->id,
                'phone_number' => $phoneNumber,
            ],
            [
                'push_name' => $pushName,
                'labels' => [],
                'metadata' => [],
            ]
        );
        
        // Update push_name if changed
        if ($pushName && $contact->push_name !== $pushName) {
            $contact->update(['push_name' => $pushName]);
        }
        
        // Check for duplicate message
        $existingMessage = Message::where('message_id', $messageId)
            ->where('channel_id', $channel->id)
            ->first();
            
        if ($existingMessage) {
            return $existingMessage;
        }
        
        // Create message
        $message = Message::create([
            'contact_id' => $contact->id,
            'channel_id' => $channel->id,
            'message_id' => $messageId,
            'direction' => 'incoming',
            'type' => $messageType,
            'content' => $content,
            'media_url' => $mediaUrl,
            'media_mime_type' => $mediaMimeType,
            'status' => 'delivered',
            'is_read' => false,
            'metadata' => $data,
            'sent_at' => isset($data['messageTimestamp']) 
                ? \Carbon\Carbon::createFromTimestamp($data['messageTimestamp']) 
                : now(),
        ]);
        
        return $message;
    }

    /**
     * Get conversation messages with pagination.
     * Requirements: 3.4
     */
    public function getConversationMessages(
        int $contactId, 
        int $channelId, 
        int $limit = 50, 
        ?int $beforeId = null
    ): Collection {
        $query = Message::where('contact_id', $contactId)
            ->where('channel_id', $channelId);
        
        if ($beforeId !== null) {
            $query->where('id', '<', $beforeId);
        }
        
        return $query->orderBy('sent_at', 'asc')
            ->orderBy('id', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Mark all incoming messages in a conversation as read.
     * Requirements: 2.5
     */
    public function markMessagesAsRead(int $contactId, int $channelId): void
    {
        Message::where('contact_id', $contactId)
            ->where('channel_id', $channelId)
            ->where('direction', 'incoming')
            ->where('is_read', false)
            ->update(['is_read' => true]);
    }


    /**
     * Normalize phone number to standard format.
     */
    private function normalizePhoneNumber(string $phoneNumber): string
    {
        // Remove all non-numeric characters except leading +
        $normalized = preg_replace('/[^0-9+]/', '', $phoneNumber);
        
        // Remove leading + if present
        $normalized = ltrim($normalized, '+');
        
        return $normalized;
    }

    /**
     * Extract phone number from WhatsApp JID.
     */
    private function extractPhoneFromJid(string $jid): string
    {
        // JID format: 573001234567@s.whatsapp.net
        return explode('@', $jid)[0] ?? $jid;
    }

    /**
     * Determine message type from webhook data.
     */
    private function determineMessageType(array $messageData): string
    {
        if (isset($messageData['conversation']) || isset($messageData['extendedTextMessage'])) {
            return 'text';
        }
        if (isset($messageData['imageMessage'])) {
            return 'image';
        }
        if (isset($messageData['documentMessage'])) {
            return 'document';
        }
        if (isset($messageData['audioMessage'])) {
            return 'audio';
        }
        if (isset($messageData['videoMessage'])) {
            return 'video';
        }
        
        return 'text';
    }

    /**
     * Extract message content based on type.
     */
    private function extractMessageContent(array $messageData, string $type): ?string
    {
        return match ($type) {
            'text' => $messageData['conversation'] 
                ?? $messageData['extendedTextMessage']['text'] 
                ?? null,
            'image' => $messageData['imageMessage']['caption'] ?? null,
            'document' => $messageData['documentMessage']['fileName'] ?? null,
            'audio' => '[Audio]',
            'video' => $messageData['videoMessage']['caption'] ?? null,
            default => null,
        };
    }

    /**
     * Extract media URL based on type.
     */
    private function extractMediaUrl(array $messageData, string $type): ?string
    {
        return match ($type) {
            'image' => $messageData['imageMessage']['url'] ?? null,
            'document' => $messageData['documentMessage']['url'] ?? null,
            'audio' => $messageData['audioMessage']['url'] ?? null,
            'video' => $messageData['videoMessage']['url'] ?? null,
            default => null,
        };
    }

    /**
     * Extract media MIME type based on type.
     */
    private function extractMediaMimeType(array $messageData, string $type): ?string
    {
        return match ($type) {
            'image' => $messageData['imageMessage']['mimetype'] ?? null,
            'document' => $messageData['documentMessage']['mimetype'] ?? null,
            'audio' => $messageData['audioMessage']['mimetype'] ?? null,
            'video' => $messageData['videoMessage']['mimetype'] ?? null,
            default => null,
        };
    }
}
