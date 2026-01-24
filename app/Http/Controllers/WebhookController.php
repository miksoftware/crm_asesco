<?php

namespace App\Http\Controllers;

use App\Models\LidMapping;
use App\Services\MessageService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Controller for handling Evolution API webhooks.
 * Requirements: 9.1, 9.4
 */
class WebhookController extends Controller
{
    public function __construct(
        private MessageService $messageService,
        private NotificationService $notificationService
    ) {}

    /**
     * Handle incoming webhook from Evolution API.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function handleEvolutionWebhook(Request $request): JsonResponse
    {
        $payload = $request->all();
        
        // Validate that request comes from Evolution API server
        // For now, we validate by checking the instance exists in our database
        $instanceName = $payload['instance'] ?? null;
        if ($instanceName) {
            $channel = \App\Models\Channel::where('instance_name', $instanceName)->first();
            if (!$channel) {
                Log::warning('Webhook received for unknown instance', [
                    'instance' => $instanceName,
                    'ip' => $request->ip(),
                ]);
                return response()->json(['error' => 'Unknown instance'], 404);
            }
        }
        
        // Log payload for debugging
        Log::info('=== WEBHOOK RAW PAYLOAD ===', [
            'full_payload' => json_encode($payload, JSON_PRETTY_PRINT),
        ]);
        
        // Si es un mensaje, loguear los campos del key específicamente
        if (isset($payload['data']['key'])) {
            Log::info('=== WEBHOOK KEY FIELDS ===', [
                'remoteJid' => $payload['data']['key']['remoteJid'] ?? 'NO EXISTE',
                'remoteJidAlt' => $payload['data']['key']['remoteJidAlt'] ?? 'NO EXISTE',
                'participant' => $payload['data']['key']['participant'] ?? 'NO EXISTE',
            ]);
            
            // ⭐ AUTO-CREATE LID MAPPING if both remoteJid and remoteJidAlt exist
            $remoteJid = $payload['data']['key']['remoteJid'] ?? null;
            $remoteJidAlt = $payload['data']['key']['remoteJidAlt'] ?? null;
            
            if ($remoteJid && $remoteJidAlt && $remoteJid !== $remoteJidAlt) {
                $this->createLidMappingFromJids($remoteJid, $remoteJidAlt, $payload['instance'] ?? null);
            }
        }
        
        // Log incoming webhook for debugging
        Log::info('Evolution API Webhook received', [
            'event' => $payload['event'] ?? 'unknown',
            'instance' => $payload['instance'] ?? 'unknown',
        ]);

        // Validate basic payload structure
        if (!$this->isValidPayload($payload)) {
            Log::warning('Invalid webhook payload received', ['payload' => $payload]);
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        try {
            $event = $payload['event'] ?? '';
            
            return match ($event) {
                'messages.upsert' => $this->processMessageReceived($payload),
                'messages.update' => $this->processMessageStatus($payload),
                'send.message' => $this->processSentMessage($payload),
                'connection.update' => $this->processConnectionUpdate($payload),
                default => response()->json(['status' => 'ignored', 'event' => $event]),
            };
        } catch (\Exception $e) {
            Log::error('Error processing webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $payload,
            ]);
            
            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * Validate the webhook payload has required fields.
     */
    private function isValidPayload(array $payload): bool
    {
        // Must have instance and event
        if (empty($payload['instance']) || empty($payload['event'])) {
            return false;
        }

        return true;
    }

    /**
     * Process incoming message webhook.
     * Requirements: 9.1, 9.2
     */
    private function processMessageReceived(array $payload): JsonResponse
    {
        $data = $payload['data'] ?? [];
        
        // Skip if no message data
        if (empty($data['key']) || empty($data['message'])) {
            Log::debug('Skipping webhook - no message data', ['payload' => $payload]);
            return response()->json(['status' => 'skipped', 'reason' => 'no_message_data']);
        }

        // Skip outgoing messages (fromMe = true)
        if (($data['key']['fromMe'] ?? false) === true) {
            Log::debug('Skipping outgoing message webhook');
            return response()->json(['status' => 'skipped', 'reason' => 'outgoing_message']);
        }

        // Skip group messages
        $remoteJid = $data['key']['remoteJid'] ?? '';
        if (str_contains($remoteJid, '@g.us')) {
            Log::debug('Skipping group message webhook');
            return response()->json(['status' => 'skipped', 'reason' => 'group_message']);
        }

        // Skip status/broadcast messages
        if (str_contains($remoteJid, '@broadcast') || str_contains($remoteJid, 'status@')) {
            Log::debug('Skipping status/broadcast message webhook');
            return response()->json(['status' => 'skipped', 'reason' => 'status_broadcast']);
        }

        // Skip newsletter messages
        if (str_contains($remoteJid, '@newsletter')) {
            Log::debug('Skipping newsletter message webhook');
            return response()->json(['status' => 'skipped', 'reason' => 'newsletter']);
        }

        // Skip reactions
        if (isset($data['message']['reactionMessage'])) {
            Log::debug('Skipping reaction message webhook');
            return response()->json(['status' => 'skipped', 'reason' => 'reaction']);
        }

        // Skip protocol messages (except revoke/delete)
        if (isset($data['message']['protocolMessage'])) {
            $protoType = $data['message']['protocolMessage']['type'] ?? null;
            if ($protoType !== 'REVOKE' && $protoType !== 0) {
                Log::debug('Skipping protocol message webhook');
                return response()->json(['status' => 'skipped', 'reason' => 'protocol_message']);
            }
        }

        try {
            // Process the incoming message
            $message = $this->messageService->processIncomingMessage($payload);
            
            // Create notification for users assigned to this channel
            $this->notificationService->createMessageNotification($message);
            
            // Broadcast new message event for real-time updates
            $this->broadcastNewMessage($message);
            
            Log::info('Incoming message processed successfully', [
                'message_id' => $message->id,
                'contact_id' => $message->contact_id,
                'channel_id' => $message->channel_id,
            ]);
            
            return response()->json([
                'status' => 'processed',
                'message_id' => $message->id,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::warning('Channel not found for webhook', [
                'instance' => $payload['instance'],
            ]);
            return response()->json(['error' => 'Channel not found'], 404);
        }
    }

    /**
     * Process sent message webhook (send.message event).
     * 
     * This captures the real phone number when we send a message,
     * so we can create LID mappings when the status update arrives with a LID.
     */
    private function processSentMessage(array $payload): JsonResponse
    {
        $data = $payload['data'] ?? [];
        $instanceName = $payload['instance'] ?? null;
        
        $messageId = $data['key']['id'] ?? null;
        $remoteJid = $data['key']['remoteJid'] ?? null;
        
        if (!$messageId || !$remoteJid) {
            return response()->json(['status' => 'skipped', 'reason' => 'missing_data']);
        }
        
        // Extract phone number from JID
        $jidPart = explode('@', $remoteJid)[0];
        $cleanNumber = explode(':', $jidPart)[0];
        $isRealPhone = preg_match('/^[1-9]\d{9,14}$/', $cleanNumber);
        
        if ($isRealPhone) {
            // Cache the real phone number for this message ID
            // When messages.update arrives with LID, we can create the mapping
            $cacheKey = "lid_mapping:{$messageId}";
            Cache::put($cacheKey, $cleanNumber, 300); // 5 minutes
            
            Log::debug('Cached phone from send.message for LID mapping', [
                'messageId' => $messageId,
                'phone' => $cleanNumber,
            ]);
        }
        
        return response()->json(['status' => 'processed']);
    }

    /**
     * Process message status update webhook.
     * 
     * IMPORTANT: This also captures LID ↔ phone number mappings.
     * WhatsApp sends multiple status updates for the same message with different JID formats.
     */
    private function processMessageStatus(array $payload): JsonResponse
    {
        $data = $payload['data'] ?? [];
        $instanceName = $payload['instance'] ?? null;
        
        // Get message identifiers - can be in different places
        $messageId = $data['keyId'] ?? $data['key']['id'] ?? null;
        $remoteJid = $data['remoteJid'] ?? $data['key']['remoteJid'] ?? null;
        $status = $data['status'] ?? null;
        
        if (!$messageId) {
            return response()->json(['status' => 'skipped', 'reason' => 'missing_message_id']);
        }

        // ⭐ LID MAPPING LOGIC
        // When we receive a status update, check if we can create a LID mapping
        if ($remoteJid && $messageId) {
            $this->processLidMapping($messageId, $remoteJid, $instanceName);
            
            // ⭐ AUTO-FIX: If this is a LID and we have a mapping, fix any existing LID contact
            if (str_contains($remoteJid, '@lid')) {
                $lid = explode('@', $remoteJid)[0];
                $phoneNumber = LidMapping::findPhoneByLid($lid);
                
                if ($phoneNumber && $instanceName) {
                    $channel = \App\Models\Channel::where('instance_name', $instanceName)->first();
                    if ($channel) {
                        // Find and fix any contact with this LID as phone_number
                        $lidContact = \App\Models\Contact::where('channel_id', $channel->id)
                            ->where('phone_number', $lid)
                            ->first();
                        
                        if ($lidContact) {
                            // Check if there's already a contact with the real phone
                            $realContact = \App\Models\Contact::where('channel_id', $channel->id)
                                ->where('phone_number', $phoneNumber)
                                ->first();
                            
                            if ($realContact && $realContact->id !== $lidContact->id) {
                                // Merge LID contact into real contact
                                \App\Models\Message::where('contact_id', $lidContact->id)
                                    ->update(['contact_id' => $realContact->id]);
                                $lidContact->labelRelations()->detach();
                                $lidContact->delete();
                                
                                Log::info('Auto-merged LID contact into real contact', [
                                    'lid' => $lid,
                                    'phone' => $phoneNumber,
                                    'merged_contact_id' => $lidContact->id,
                                    'into_contact_id' => $realContact->id,
                                ]);
                            } else {
                                // Update LID contact with real phone number
                                $lidContact->update([
                                    'phone_number' => $phoneNumber,
                                    'remote_jid' => $phoneNumber . '@s.whatsapp.net',
                                ]);
                                
                                Log::info('Auto-fixed LID contact with real phone', [
                                    'lid' => $lid,
                                    'phone' => $phoneNumber,
                                    'contact_id' => $lidContact->id,
                                ]);
                            }
                        }
                    }
                }
            }
        }

        // Map Evolution API status to our status
        if ($status) {
            $mappedStatus = match ($status) {
                'DELIVERY_ACK', 'PLAYED' => 'delivered',
                'READ' => 'read',
                'PENDING' => 'pending',
                'SERVER_ACK' => 'sent',
                default => null,
            };

            if ($mappedStatus) {
                \App\Models\Message::where('message_id', $messageId)
                    ->update(['status' => $mappedStatus]);
                    
                Log::debug('Message status updated', [
                    'message_id' => $messageId,
                    'status' => $mappedStatus,
                ]);
            }
        }

        return response()->json(['status' => 'processed']);
    }

    /**
     * Process LID mapping from webhook data.
     * 
     * WhatsApp sends multiple webhooks for the same message:
     * - One with real phone number: 573028537828@s.whatsapp.net
     * - One with LID: 177562672193615@lid or 573028537828:39@s.whatsapp.net
     * 
     * We use a cache to temporarily store messageId → phoneNumber,
     * then when we see the same messageId with a LID, we create the mapping.
     */
    private function processLidMapping(string $messageId, string $remoteJid, ?string $instanceName): void
    {
        $cacheKey = "lid_mapping:{$messageId}";
        $cacheTtl = 300; // 5 minutes
        
        // Extract the identifier part
        $jidPart = explode('@', $remoteJid)[0];
        
        // Check if this is a LID format
        $isLid = str_contains($remoteJid, '@lid') || str_contains($jidPart, ':');
        
        // Extract clean number (before any : if present)
        $cleanNumber = explode(':', $jidPart)[0];
        
        // Check if this looks like a real phone number (10-15 digits, starts with 1-9)
        $isRealPhone = preg_match('/^[1-9]\d{9,14}$/', $cleanNumber);
        
        if ($isLid) {
            // This is a LID - check if we have a cached phone number for this message
            $cachedPhone = Cache::get($cacheKey);
            
            if ($cachedPhone && $cachedPhone !== $cleanNumber) {
                // We have a real phone number cached, create the mapping
                // The LID is the part that's NOT a real phone number
                $lidPart = $isRealPhone ? null : $cleanNumber;
                
                // If the JID has a colon, the LID might be after it
                if (str_contains($jidPart, ':')) {
                    $parts = explode(':', $jidPart);
                    // The real phone is usually the first part if it looks like a phone
                    if (preg_match('/^[1-9]\d{9,14}$/', $parts[0])) {
                        // First part is phone, this is format like 573028537828:39@s.whatsapp.net
                        // Not a true LID, but we can still use the phone number
                        Log::debug('JID with suffix detected', [
                            'messageId' => $messageId,
                            'remoteJid' => $remoteJid,
                            'phone' => $parts[0],
                        ]);
                    }
                }
                
                // If we found a true LID (non-phone identifier), create mapping
                if ($lidPart && !preg_match('/^[1-9]\d{9,14}$/', $lidPart)) {
                    $channel = $instanceName ? \App\Models\Channel::where('instance_name', $instanceName)->first() : null;
                    
                    LidMapping::createMapping(
                        $lidPart,
                        $cachedPhone,
                        $messageId,
                        $channel?->id
                    );
                    
                    Log::info('LID mapping created from webhook', [
                        'lid' => $lidPart,
                        'phone' => $cachedPhone,
                        'messageId' => $messageId,
                    ]);
                }
            } elseif (!$cachedPhone && $isRealPhone) {
                // This LID JID contains a real phone number, cache it
                Cache::put($cacheKey, $cleanNumber, $cacheTtl);
            }
        } elseif ($isRealPhone) {
            // This is a real phone number - cache it for potential LID matching
            $existingCache = Cache::get($cacheKey);
            
            if (!$existingCache) {
                Cache::put($cacheKey, $cleanNumber, $cacheTtl);
                Log::debug('Cached phone number for LID mapping', [
                    'messageId' => $messageId,
                    'phone' => $cleanNumber,
                ]);
            } elseif ($existingCache !== $cleanNumber) {
                // We have a different number cached - one might be a LID
                // Check which one is the real phone and which is the LID
                $existingIsPhone = preg_match('/^[1-9]\d{9,14}$/', $existingCache);
                
                if ($existingIsPhone && !$isRealPhone) {
                    // Existing is phone, current is LID
                    $channel = $instanceName ? \App\Models\Channel::where('instance_name', $instanceName)->first() : null;
                    LidMapping::createMapping($cleanNumber, $existingCache, $messageId, $channel?->id);
                    Log::info('LID mapping created (reverse)', ['lid' => $cleanNumber, 'phone' => $existingCache]);
                } elseif (!$existingIsPhone && $isRealPhone) {
                    // Existing is LID, current is phone
                    $channel = $instanceName ? \App\Models\Channel::where('instance_name', $instanceName)->first() : null;
                    LidMapping::createMapping($existingCache, $cleanNumber, $messageId, $channel?->id);
                    Log::info('LID mapping created', ['lid' => $existingCache, 'phone' => $cleanNumber]);
                }
            }
        }
    }

    /**
     * Process connection status update webhook.
     */
    private function processConnectionUpdate(array $payload): JsonResponse
    {
        $instanceName = $payload['instance'] ?? null;
        $data = $payload['data'] ?? [];
        $state = $data['state'] ?? null;
        
        if (!$instanceName) {
            return response()->json(['status' => 'skipped']);
        }

        $channel = \App\Models\Channel::where('instance_name', $instanceName)->first();
        
        if ($channel) {
            $newStatus = match ($state) {
                'open' => 'connected',
                'close' => 'disconnected',
                'connecting' => 'connecting',
                default => $channel->status,
            };
            
            $channel->update(['status' => $newStatus]);
            
            Log::info('Channel connection status updated', [
                'channel_id' => $channel->id,
                'instance' => $instanceName,
                'status' => $newStatus,
            ]);
        }

        return response()->json(['status' => 'processed']);
    }

    /**
     * Create LID mapping from remoteJid and remoteJidAlt.
     * 
     * Evolution API sends:
     * - remoteJid: The primary JID (usually the real phone number)
     * - remoteJidAlt: The alternative JID (usually the LID)
     */
    private function createLidMappingFromJids(string $remoteJid, string $remoteJidAlt, ?string $instanceName): void
    {
        // Extract the identifier parts
        $jid1Part = explode('@', $remoteJid)[0];
        $jid2Part = explode('@', $remoteJidAlt)[0];
        
        // Clean any :XX suffix
        $clean1 = explode(':', $jid1Part)[0];
        $clean2 = explode(':', $jid2Part)[0];
        
        // Determine which is the phone and which is the LID
        $isPhone1 = preg_match('/^[1-9]\d{9,14}$/', $clean1);
        $isPhone2 = preg_match('/^[1-9]\d{9,14}$/', $clean2);
        
        $phoneNumber = null;
        $lid = null;
        
        if ($isPhone1 && !$isPhone2) {
            $phoneNumber = $clean1;
            $lid = $clean2;
        } elseif ($isPhone2 && !$isPhone1) {
            $phoneNumber = $clean2;
            $lid = $clean1;
        } else {
            // Both look like phones or both look like LIDs - can't determine
            return;
        }
        
        // Don't create mapping if LID looks like a phone number
        if (preg_match('/^[1-9]\d{9,14}$/', $lid)) {
            return;
        }
        
        $channel = $instanceName ? \App\Models\Channel::where('instance_name', $instanceName)->first() : null;
        
        // Check if mapping already exists
        $existing = LidMapping::where('lid', $lid)->first();
        if (!$existing) {
            LidMapping::create([
                'lid' => $lid,
                'phone_number' => $phoneNumber,
                'channel_id' => $channel?->id,
            ]);
            
            Log::info('LID mapping created from remoteJidAlt', [
                'lid' => $lid,
                'phone' => $phoneNumber,
                'remoteJid' => $remoteJid,
                'remoteJidAlt' => $remoteJidAlt,
            ]);
        }
    }

    /**
     * Broadcast new message event for real-time UI updates.
     * Requirements: 9.2, 9.3
     */
    private function broadcastNewMessage(\App\Models\Message $message): void
    {
        // Dispatch Livewire event for real-time updates
        // This will be picked up by the Chat\Index and NotificationBadge components
        event(new \App\Events\NewMessageReceived($message));
    }
}
