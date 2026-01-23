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
            // Use remote_jid if available (for LID contacts), otherwise use phone number
            $recipient = $contact->remote_jid ?? $phoneNumber;
            
            // If remote_jid doesn't contain @, it's a phone number, format it
            if ($recipient && !str_contains($recipient, '@')) {
                $recipient = $this->normalizePhoneNumber($recipient);
            }

            Log::info('Sending message via Evolution API', [
                'channel' => $channel->instance_name,
                'recipient' => $recipient,
                'phone_number' => $phoneNumber,
                'remote_jid' => $contact->remote_jid,
            ]);

            // Send via Evolution API
            $response = $this->evolutionApi->sendTextMessage(
                $channel->instance_name,
                $recipient,
                $text
            );

            Log::info('Evolution API sendTextMessage response', [
                'channel' => $channel->instance_name,
                'recipient' => $recipient,
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
     * 
     * IMPORTANT: Handles LID JIDs using remoteJidAlt field from Evolution API.
     */
    public function processIncomingMessage(array $webhookData): Message
    {
        $instanceName = $webhookData['instance'] ?? null;
        $data = $webhookData['data'] ?? [];
        
        // Find channel by instance name
        $channel = Channel::where('instance_name', $instanceName)->firstOrFail();
        
        // Extract message data
        $remoteJid = $data['key']['remoteJid'] ?? '';
        $remoteJidAlt = $data['key']['remoteJidAlt'] ?? null;
        $messageId = $data['key']['id'] ?? null;
        $pushName = $data['pushName'] ?? null;
        
        // ⭐ PRIORITY: Use remoteJid first, but check remoteJidAlt for real phone
        $phoneNumber = null;
        $primaryJid = $remoteJid;
        
        // Extract from primary JID
        $jidPart = explode('@', $remoteJid)[0];
        $cleanJidPart = explode(':', $jidPart)[0];
        $isRealPhone = preg_match('/^[1-9]\d{9,14}$/', $cleanJidPart);
        
        if ($isRealPhone) {
            $phoneNumber = $cleanJidPart;
        } elseif ($remoteJidAlt) {
            // Primary JID is LID, check alternative
            $altJidPart = explode('@', $remoteJidAlt)[0];
            $cleanAltPart = explode(':', $altJidPart)[0];
            $isAltRealPhone = preg_match('/^[1-9]\d{9,14}$/', $cleanAltPart);
            
            if ($isAltRealPhone) {
                $phoneNumber = $cleanAltPart;
                Log::info('Using remoteJidAlt for phone number', [
                    'remoteJid' => $remoteJid,
                    'remoteJidAlt' => $remoteJidAlt,
                    'phone' => $phoneNumber,
                ]);
            }
        }
        
        // If still no phone, try LID mapping table
        if (!$phoneNumber) {
            $phoneNumber = \App\Models\LidMapping::findPhoneByLid($cleanJidPart);
            if ($phoneNumber) {
                Log::info('Resolved LID from mapping table', [
                    'lid' => $cleanJidPart,
                    'phone' => $phoneNumber,
                ]);
            }
        }
        
        // Last resort: use whatever we have
        if (!$phoneNumber) {
            $phoneNumber = $cleanJidPart;
            Log::warning('Could not resolve real phone number', [
                'remoteJid' => $remoteJid,
                'remoteJidAlt' => $remoteJidAlt,
                'using' => $phoneNumber,
            ]);
        }
        
        // Determine message type and content
        $messageType = $this->determineMessageType($data['message'] ?? []);
        $content = $this->extractMessageContent($data['message'] ?? [], $messageType);
        $mediaUrl = $this->extractMediaUrl($data['message'] ?? [], $messageType);
        $mediaMimeType = $this->extractMediaMimeType($data['message'] ?? [], $messageType);
        
        // For media messages, try to get base64 and save locally
        if (in_array($messageType, ['image', 'video', 'audio', 'document']) && $messageId && $remoteJid) {
            $localMediaUrl = $this->downloadAndSaveMedia($instanceName, $messageId, $remoteJid, $messageType, $mediaMimeType);
            if ($localMediaUrl) {
                $mediaUrl = $localMediaUrl;
            }
        }
        
        // Standard JID format for sending messages
        $standardJid = $phoneNumber . '@s.whatsapp.net';
        
        // Find or create contact - search by phone number to unify LID and regular contacts
        $contact = Contact::where('channel_id', $channel->id)
            ->where('phone_number', $phoneNumber)
            ->first();

        if (!$contact) {
            $contact = Contact::create([
                'channel_id' => $channel->id,
                'phone_number' => $phoneNumber,
                'remote_jid' => $standardJid,
                'push_name' => $pushName,
                'labels' => [],
                'metadata' => [],
            ]);
        } else {
            // Update remote_jid to standard format and push_name if needed
            $updates = [];
            if ($contact->remote_jid !== $standardJid) {
                $updates['remote_jid'] = $standardJid;
            }
            if ($pushName && $contact->push_name !== $pushName) {
                $updates['push_name'] = $pushName;
            }
            if (!empty($updates)) {
                $contact->update($updates);
            }
        }
        
        // Check for duplicate message
        $existingMessage = Message::where('message_id', $messageId)
            ->where('channel_id', $channel->id)
            ->first();
            
        if ($existingMessage) {
            return $existingMessage;
        }
        
        // Parse timestamp - Evolution API sends Unix timestamp in UTC
        // Convert to app timezone (America/Bogota)
        $sentAt = now();
        if (isset($data['messageTimestamp'])) {
            $sentAt = \Carbon\Carbon::createFromTimestamp(
                $data['messageTimestamp'], 
                'UTC'
            )->setTimezone(config('app.timezone'));
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
            'sent_at' => $sentAt,
        ]);
        
        return $message;
    }

    /**
     * Download media from Evolution API and save locally.
     */
    private function downloadAndSaveMedia(string $instanceName, string $messageId, string $remoteJid, string $type, ?string $mimeType): ?string
    {
        try {
            $result = $this->evolutionApi->getMediaBase64($instanceName, $messageId, $remoteJid);
            
            if (!$result['success'] || empty($result['data']['base64'])) {
                Log::warning('Failed to download media from Evolution API', [
                    'instance' => $instanceName,
                    'messageId' => $messageId,
                    'error' => $result['error'] ?? 'No base64 data',
                ]);
                return null;
            }
            
            $base64 = $result['data']['base64'];
            $mimeType = $result['data']['mimetype'] ?? $mimeType ?? 'application/octet-stream';
            
            // Determine file extension from mime type
            $extension = $this->getExtensionFromMimeType($mimeType, $type);
            
            // Generate unique filename
            $filename = $type . '_' . $messageId . '.' . $extension;
            $path = 'chat-media/' . date('Y/m') . '/' . $filename;
            
            // Decode and save
            $content = base64_decode($base64);
            \Illuminate\Support\Facades\Storage::disk('public')->put($path, $content);
            
            return \Illuminate\Support\Facades\Storage::disk('public')->url($path);
        } catch (\Exception $e) {
            Log::error('Exception downloading media', [
                'instance' => $instanceName,
                'messageId' => $messageId,
                'exception' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get file extension from MIME type.
     */
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
            'audio/mpeg' => 'mp3',
            'audio/mp4' => 'm4a',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        ];
        
        if (isset($map[$mimeType])) {
            return $map[$mimeType];
        }
        
        // Default extensions by type
        return match ($type) {
            'image' => 'jpg',
            'video' => 'mp4',
            'audio' => 'ogg',
            'document' => 'bin',
            default => 'bin',
        };
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
        if (isset($messageData['documentMessage']) || isset($messageData['documentWithCaptionMessage'])) {
            return 'document';
        }
        if (isset($messageData['audioMessage']) || isset($messageData['pttMessage'])) {
            return 'audio';
        }
        if (isset($messageData['videoMessage'])) {
            return 'video';
        }
        if (isset($messageData['contactMessage']) || isset($messageData['contactsArrayMessage'])) {
            return 'contact';
        }
        if (isset($messageData['locationMessage']) || isset($messageData['liveLocationMessage'])) {
            return 'location';
        }
        if (isset($messageData['stickerMessage'])) {
            return 'sticker';
        }
        if (isset($messageData['protocolMessage'])) {
            $protoType = $messageData['protocolMessage']['type'] ?? null;
            if ($protoType === 'REVOKE' || $protoType === 0) {
                return 'deleted';
            }
        }
        if (isset($messageData['viewOnceMessage']) || isset($messageData['viewOnceMessageV2'])) {
            return 'image';
        }
        if (isset($messageData['pollCreationMessage']) || isset($messageData['pollCreationMessageV3'])) {
            return 'text';
        }
        
        return 'other';
    }

    /**
     * Extract message content based on type.
     */
    private function extractMessageContent(array $messageData, string $type): ?string
    {
        return match ($type) {
            'text' => $messageData['conversation'] 
                ?? $messageData['extendedTextMessage']['text'] 
                ?? ($messageData['pollCreationMessage']['name'] ?? null ? '📊 ' . $messageData['pollCreationMessage']['name'] : null)
                ?? ($messageData['pollCreationMessageV3']['name'] ?? null ? '📊 ' . $messageData['pollCreationMessageV3']['name'] : null)
                ?? null,
            'image' => $messageData['imageMessage']['caption'] 
                ?? ($messageData['viewOnceMessage'] ? '[Vista única]' : null)
                ?? ($messageData['viewOnceMessageV2'] ? '[Vista única]' : null)
                ?? null,
            'document' => $messageData['documentMessage']['fileName'] 
                ?? $messageData['documentWithCaptionMessage']['message']['documentMessage']['fileName'] 
                ?? '[Documento]',
            'audio' => '[Audio]',
            'video' => $messageData['videoMessage']['caption'] ?? null,
            'contact' => $messageData['contactMessage']['displayName'] 
                ?? $messageData['contactsArrayMessage']['contacts'][0]['displayName'] 
                ?? '[Contacto]',
            'location' => $messageData['locationMessage']['name'] 
                ?? $messageData['locationMessage']['address'] 
                ?? $messageData['liveLocationMessage']['caption']
                ?? '[Ubicación]',
            'sticker' => '[Sticker]',
            'deleted' => '[Mensaje eliminado]',
            'other' => '[Mensaje no soportado]',
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
            'document' => $messageData['documentMessage']['url'] 
                ?? $messageData['documentWithCaptionMessage']['message']['documentMessage']['url'] 
                ?? null,
            'audio' => $messageData['audioMessage']['url'] ?? $messageData['pttMessage']['url'] ?? null,
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
            'image' => $messageData['imageMessage']['mimetype'] ?? 'image/jpeg',
            'document' => $messageData['documentMessage']['mimetype'] 
                ?? $messageData['documentWithCaptionMessage']['message']['documentMessage']['mimetype'] 
                ?? 'application/octet-stream',
            'audio' => $messageData['audioMessage']['mimetype'] ?? $messageData['pttMessage']['mimetype'] ?? 'audio/ogg; codecs=opus',
            'video' => $messageData['videoMessage']['mimetype'] ?? 'video/mp4',
            default => null,
        };
    }
}
