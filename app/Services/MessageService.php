<?php

namespace App\Services;

use App\Jobs\DownloadMediaJob;
use App\Jobs\SendMessageJob;
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
     * Supports both individual and group messages.
     */
    public function sendTextMessage(int $channelId, string $recipient, string $text, bool $isGroup = false): Message
    {
        $channel = Channel::findOrFail($channelId);
        
        // Verify channel is connected
        if ($channel->status !== 'connected') {
            throw new \Exception("El canal '{$channel->name}' no está conectado. Estado actual: {$channel->status}");
        }
        
        if ($isGroup) {
            // For groups, find the contact by group_jid
            $contact = Contact::where('channel_id', $channelId)
                ->where('is_group', true)
                ->where('group_jid', $recipient)
                ->firstOrFail();
            
            // The recipient for Evolution API is the group JID itself
            $apiRecipient = $recipient;
        } else {
            // Find or create individual contact
            $contact = Contact::firstOrCreate(
                [
                    'channel_id' => $channelId,
                    'phone_number' => $this->normalizePhoneNumber($recipient),
                ],
                [
                    'name' => null,
                    'push_name' => null,
                    'labels' => [],
                    'metadata' => [],
                ]
            );
            
            // Use remote_jid if available (for LID contacts), otherwise use phone number
            $apiRecipient = $contact->remote_jid ?? $recipient;
            
            // If remote_jid doesn't contain @, it's a phone number, format it
            if ($apiRecipient && !str_contains($apiRecipient, '@')) {
                $apiRecipient = $this->normalizePhoneNumber($apiRecipient);
            }
        }

        // Create message with pending status
        $message = Message::create([
            'contact_id' => $contact->id,
            'channel_id' => $channelId,
            'user_id' => auth()->id(),
            'direction' => 'outgoing',
            'type' => 'text',
            'content' => $text,
            'sender_name' => $isGroup ? 'Tú' : null,
            'sender_phone' => $isGroup ? $channel->phone_number : null,
            'status' => 'pending',
            'is_read' => true,
            'sent_at' => now(),
        ]);

        // Dispatch async job to send via Evolution API
        // This returns immediately so the UI doesn't block
        SendMessageJob::dispatch(
            messageId: $message->id,
            instanceName: $channel->instance_name,
            recipient: $apiRecipient,
            text: $text,
        );

        return $message;
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
        $isFromMe = ($data['key']['fromMe'] ?? false) === true;
        
        // ⭐ GROUP MESSAGE HANDLING
        $isGroupMessage = str_contains($remoteJid, '@g.us');
        $senderName = null;
        $senderPhone = null;
        
        if ($isGroupMessage) {
            // For groups, remoteJid is the group JID, participant is the sender
            $participant = $data['key']['participant'] ?? null;
            
            // Extract sender info
            if ($participant) {
                $participantPart = explode('@', $participant)[0];
                $senderPhone = explode(':', $participantPart)[0];
            }
            $senderName = $pushName; // pushName is the sender's name in groups
            
            // For fromMe messages in groups, mark the sender as "Tú"
            if ($isFromMe) {
                $senderName = 'Tú';
                // Get our own phone from the channel
                $senderPhone = $channel->phone_number;
            }
            
            $groupJid = $remoteJid;
            // ⭐ IMPORTANT: pushName in group messages is the SENDER's name, NOT the group name
            // Only use groupName field (if present) — never fallback to pushName for group names
            $groupName = $data['groupName'] ?? null;
            
            // Find or create group contact
            $contact = Contact::where('channel_id', $channel->id)
                ->where('is_group', true)
                ->where('group_jid', $groupJid)
                ->first();
            
            if (!$contact) {
                // Use the group JID part as phone_number placeholder (unique per group)
                $groupId = explode('@', $groupJid)[0];
                
                // If we don't have the group name from the webhook, try to fetch it from the API
                if (!$groupName) {
                    try {
                        $evolutionApi = app(EvolutionApiService::class);
                        $groupInfo = $evolutionApi->findGroupInfo($channel->instance_name, $groupJid);
                        if ($groupInfo['success'] && !empty($groupInfo['data'])) {
                            $groupName = $groupInfo['data']['subject'] ?? null;
                        }
                    } catch (\Exception $e) {
                        Log::debug('Could not fetch group info', ['groupJid' => $groupJid, 'error' => $e->getMessage()]);
                    }
                }
                
                $contact = Contact::create([
                    'channel_id' => $channel->id,
                    'phone_number' => $groupId,
                    'remote_jid' => $groupJid,
                    'is_group' => true,
                    'group_jid' => $groupJid,
                    'push_name' => $groupName,
                    'name' => $groupName,
                    'labels' => [],
                    'metadata' => [],
                ]);
            } else {
                // Update group name if we got a real group name from the API
                if ($groupName && $groupName !== $contact->name) {
                    $contact->update(['name' => $groupName, 'push_name' => $groupName]);
                }
            }
        } else {
            // ⭐ INDIVIDUAL MESSAGE HANDLING (existing logic)
            $phoneNumber = null;
            
            // Extract from primary JID
            $jidPart = explode('@', $remoteJid)[0];
            $cleanJidPart = explode(':', $jidPart)[0];
            $isRealPhone = preg_match('/^[1-9]\d{9,14}$/', $cleanJidPart);
            
            if ($isRealPhone) {
                $phoneNumber = $cleanJidPart;
            } elseif ($remoteJidAlt) {
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
            
            if (!$phoneNumber) {
                $phoneNumber = \App\Models\LidMapping::findPhoneByLid($cleanJidPart);
                if ($phoneNumber) {
                    Log::info('Resolved LID from mapping table', [
                        'lid' => $cleanJidPart,
                        'phone' => $phoneNumber,
                    ]);
                }
            }
            
            if (!$phoneNumber) {
                $phoneNumber = $cleanJidPart;
                Log::warning('Could not resolve real phone number', [
                    'remoteJid' => $remoteJid,
                    'remoteJidAlt' => $remoteJidAlt,
                    'using' => $phoneNumber,
                ]);
            }
            
            $standardJid = $phoneNumber . '@s.whatsapp.net';
            
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
                $updates = [];
                if ($contact->remote_jid !== $standardJid) {
                    $updates['remote_jid'] = $standardJid;
                }
                if ($pushName && $contact->push_name !== $pushName) {
                    $updates['push_name'] = $pushName;
                    // Also update name if contact has no custom name set
                    if (!$contact->name) {
                        $updates['name'] = $pushName;
                    }
                }
                if (!empty($updates)) {
                    $contact->update($updates);
                }
            }
        }
        
        // Normalize message data (unwrap viewOnce messages, etc.)
        $normalizedMessage = $this->normalizeMessageData($data['message'] ?? []);
        
        // Determine message type and content
        $messageType = $this->determineMessageType($normalizedMessage);
        $content = $this->extractMessageContent($normalizedMessage, $messageType);
        $mediaUrl = $this->extractMediaUrl($normalizedMessage, $messageType);
        $mediaMimeType = $this->extractMediaMimeType($normalizedMessage, $messageType);
        
        // For media messages, check for inline base64 first (instant), otherwise dispatch background job
        $shouldDispatchMediaJob = false;
        if (in_array($messageType, ['image', 'video', 'audio', 'document', 'sticker']) && $messageId) {
            $inlineBase64 = $data['message']['base64'] ?? null;
            
            if ($inlineBase64) {
                // Inline base64 available - save immediately (fast, no HTTP call)
                $localMediaUrl = $this->saveBase64Media($inlineBase64, $messageType, $messageId, $mediaMimeType);
                if ($localMediaUrl) {
                    $mediaUrl = $localMediaUrl;
                }
            } else {
                // No inline base64 - will dispatch background job after message is created
                $shouldDispatchMediaJob = true;
            }
        }
        
        // Check for duplicate message
        $existingMessage = Message::where('message_id', $messageId)
            ->where('channel_id', $channel->id)
            ->first();
            
        if ($existingMessage) {
            return $existingMessage;
        }
        
        // Parse timestamp
        $sentAt = now();
        if (isset($data['messageTimestamp'])) {
            $sentAt = \Carbon\Carbon::createFromTimestamp(
                $data['messageTimestamp'], 
                'UTC'
            )->setTimezone(config('app.timezone'));
        }

        // Determine direction for group messages
        $direction = ($isGroupMessage && $isFromMe) ? 'outgoing' : ($isGroupMessage ? 'incoming' : 'incoming');

        // Create message
        $message = Message::create([
            'contact_id' => $contact->id,
            'channel_id' => $channel->id,
            'message_id' => $messageId,
            'direction' => $direction,
            'type' => $messageType,
            'content' => $content,
            'media_url' => $mediaUrl,
            'media_mime_type' => $mediaMimeType,
            'sender_name' => $senderName,
            'sender_phone' => $senderPhone,
            'status' => 'delivered',
            'is_read' => $isFromMe,
            'metadata' => $data,
            'sent_at' => $sentAt,
        ]);

        // Dispatch background job to download media if needed
        if ($shouldDispatchMediaJob && $messageId) {
            DownloadMediaJob::dispatch(
                messageId: $message->id,
                instanceName: $instanceName,
                externalMessageId: $messageId,
                remoteJid: $remoteJid,
                type: $messageType,
                mimeType: $mediaMimeType,
            );
        }
        
        return $message;
    }

    /**
     * Save inline base64 media directly (fast, no HTTP call).
     * Used when Evolution API includes base64 in the webhook payload.
     */
    private function saveBase64Media(string $base64, string $type, string $messageId, ?string $mimeType): ?string
    {
        try {
            $resolvedMimeType = $mimeType ?? 'application/octet-stream';
            $extension = $this->getExtensionFromMimeType($resolvedMimeType, $type);
            $filename = $type . '_' . $messageId . '.' . $extension;
            $path = 'chat-media/' . date('Y/m') . '/' . $filename;

            $content = base64_decode($base64);
            if ($content === false || strlen($content) === 0) {
                return null;
            }

            \Illuminate\Support\Facades\Storage::disk('public')->put($path, $content);
            return \Illuminate\Support\Facades\Storage::disk('public')->url($path);
        } catch (\Exception $e) {
            Log::warning('Failed to save inline base64 media', [
                'messageId' => $messageId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Normalize message data by unwrapping viewOnce messages.
     * This ensures all extract methods can work with a flat structure.
     */
    private function normalizeMessageData(array $messageData): array
    {
        // Unwrap viewOnceMessage
        if (isset($messageData['viewOnceMessage']['message'])) {
            $inner = $messageData['viewOnceMessage']['message'];
            $inner['_isViewOnce'] = true;
            return $inner;
        }
        // Unwrap viewOnceMessageV2
        if (isset($messageData['viewOnceMessageV2']['message'])) {
            $inner = $messageData['viewOnceMessageV2']['message'];
            $inner['_isViewOnce'] = true;
            return $inner;
        }
        return $messageData;
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
        // viewOnceMessage is handled by normalizeMessageData() before this method is called
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
                ?? (($messageData['_isViewOnce'] ?? false) ? '[Vista única]' : null)
                ?? null,
            'document' => $messageData['documentMessage']['fileName'] 
                ?? $messageData['documentWithCaptionMessage']['message']['documentMessage']['fileName'] 
                ?? '[Documento]',
            'audio' => '[Audio]',
            'video' => $messageData['videoMessage']['caption'] 
                ?? (($messageData['_isViewOnce'] ?? false) ? '[Vista única]' : null)
                ?? null,
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
            'sticker' => $messageData['stickerMessage']['url'] ?? null,
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
            'sticker' => $messageData['stickerMessage']['mimetype'] ?? 'image/webp',
            default => null,
        };
    }
}
