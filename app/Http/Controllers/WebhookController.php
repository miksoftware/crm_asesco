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

        // NOTA: Evolution API envía un campo "apikey" en el body del webhook,
        // pero es el token/hash de la instancia, NO el apikey global.
        // La validación se hace verificando que la instancia exista en nuestra BD.

        // Validar que la instancia existe en nuestra base de datos
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
        
        // AUTO-CREATE LID MAPPING if both remoteJid and remoteJidAlt exist
        if (isset($payload['data']['key'])) {
            $remoteJid = $payload['data']['key']['remoteJid'] ?? null;
            $remoteJidAlt = $payload['data']['key']['remoteJidAlt'] ?? null;
            
            if ($remoteJid && $remoteJidAlt && $remoteJid !== $remoteJidAlt) {
                $this->createLidMappingFromJids($remoteJid, $remoteJidAlt, $payload['instance'] ?? null);
            }
        }
        
        Log::info('Webhook recibido', [
            'event' => $payload['event'] ?? 'unknown',
            'instance' => $instanceName,
            'remoteJid' => $payload['data']['key']['remoteJid'] ?? null,
        ]);

        // Validate basic payload structure
        if (!$this->isValidPayload($payload)) {
            Log::warning('Invalid webhook payload received', ['payload' => $payload]);
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        try {
            $event = $payload['event'] ?? '';
            
            // Evolution API sends events in lowercase dot notation: messages.upsert
            // Normalize just in case
            $event = strtolower(str_replace('_', '.', $event));
            
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
                'event' => $payload['event'] ?? 'unknown',
                'instance' => $instanceName,
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

        // Skip outgoing messages ONLY for groups (group self-messages are handled separately)
        // Individual outgoing messages (sent from phone) are processed to maintain complete history
        $remoteJid = $data['key']['remoteJid'] ?? '';
        $isGroupMessage = str_contains($remoteJid, '@g.us');

        // Skip status/broadcast messages
        if (str_contains($remoteJid, '@broadcast') || str_contains($remoteJid, 'status@')) {
            return response()->json(['status' => 'skipped', 'reason' => 'status_broadcast']);
        }

        // Skip newsletter messages
        if (str_contains($remoteJid, '@newsletter')) {
            return response()->json(['status' => 'skipped', 'reason' => 'newsletter']);
        }

        // Skip reactions
        if (isset($data['message']['reactionMessage'])) {
            return response()->json(['status' => 'skipped', 'reason' => 'reaction']);
        }

        // Deduplicación: verificar si ya fue procesado por WebSocket
        $externalMessageId = $data['key']['id'] ?? null;
        if ($externalMessageId) {
            $dedupeKey = "msg_processed:upsert:{$externalMessageId}";
            if (Cache::has($dedupeKey)) {
                return response()->json(['status' => 'skipped', 'reason' => 'already_processed_by_ws']);
            }
            // Marcar como procesado para que el WebSocket lo ignore si llega después
            Cache::put($dedupeKey, true, 60);
        }

        // Skip protocol messages (except revoke/delete)
        if (isset($data['message']['protocolMessage'])) {
            $protoType = $data['message']['protocolMessage']['type'] ?? null;
            if ($protoType !== 'REVOKE' && $protoType !== 0) {
                return response()->json(['status' => 'skipped', 'reason' => 'protocol_message']);
            }
        }

        try {
            // Process the incoming message
            $message = $this->messageService->processIncomingMessage($payload);
            
            // If message was skipped (e.g., unresolvable LID contact), return early
            if (!$message) {
                return response()->json(['status' => 'skipped', 'reason' => 'unresolvable_contact']);
            }
            
            // Si el mensaje ya existía (deduplicado), no re-notificar ni re-emitir.
            // processIncomingMessage retorna el mensaje existente si ya estaba en BD.
            // Verificamos si fue recién creado comparando created_at con ahora.
            $isNewMessage = $message->wasRecentlyCreated;
            
            if ($isNewMessage) {
                // Create notification for users assigned to this channel
                $this->notificationService->createMessageNotification($message);
                
                // Broadcast new message event for real-time updates
                $this->broadcastNewMessage($message);
            }
            
            return response()->json([
                'status' => $isNewMessage ? 'processed' : 'deduplicated',
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

        // Deduplicación: verificar si ya fue procesado por WebSocket
        if ($status) {
            $dedupeKey = "msg_processed:status:{$messageId}:{$status}";
            if (Cache::has($dedupeKey)) {
                return response()->json(['status' => 'skipped', 'reason' => 'already_processed_by_ws']);
            }
            Cache::put($dedupeKey, true, 60);
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
                $updatedMessage = \App\Models\Message::where('message_id', $messageId)->first();
                if ($updatedMessage) {
                    $updatedMessage->update(['status' => $mappedStatus]);

                    // Emitir evento de Broadcasting para actualizar checks en el frontend
                    try {
                        broadcast(new \App\Events\MessageStatusUpdated(
                            messageId: $updatedMessage->id,
                            externalMessageId: $messageId,
                            status: $mappedStatus,
                            contactId: $updatedMessage->contact_id,
                            channelId: $updatedMessage->channel_id,
                        ));
                    } catch (\Exception $e) {
                        // Broadcasting no disponible, no es crítico
                    }
                }
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
            } elseif ($existingCache !== $cleanNumber) {
                // We have a different number cached - one might be a LID
                // Check which one is the real phone and which is the LID
                $existingIsPhone = preg_match('/^[1-9]\d{9,14}$/', $existingCache);
                
                if ($existingIsPhone && !$isRealPhone) {
                    // Existing is phone, current is LID
                    $channel = $instanceName ? \App\Models\Channel::where('instance_name', $instanceName)->first() : null;
                    LidMapping::createMapping($cleanNumber, $existingCache, $messageId, $channel?->id);
                } elseif (!$existingIsPhone && $isRealPhone) {
                    // Existing is LID, current is phone
                    $channel = $instanceName ? \App\Models\Channel::where('instance_name', $instanceName)->first() : null;
                    LidMapping::createMapping($existingCache, $cleanNumber, $messageId, $channel?->id);
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

            // Notificar al frontend via broadcasting para actualizar el estado en tiempo real
            try {
                broadcast(new \App\Events\ChannelStatusUpdated(
                    $channel->id,
                    $newStatus,
                    $instanceName,
                ))->toOthers();
            } catch (\Exception $e) {
                Log::warning('Error broadcasting connection update', ['error' => $e->getMessage()]);
            }

            Log::info("Canal {$instanceName} actualizado a: {$newStatus}");
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
        }
    }

    /**
     * Broadcast new message event for real-time UI updates.
     * Requirements: 9.2, 9.3
     */
    private function broadcastNewMessage(\App\Models\Message $message): void
    {
        // Dispatch evento interno de Laravel (para listeners como BroadcastNewMessage)
        event(new \App\Events\NewMessageReceived($message));

        // Emitir evento de Broadcasting para el frontend en tiempo real (Reverb)
        try {
            broadcast(new \App\Events\NewWhatsAppMessage($message));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::debug('Broadcasting no disponible', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
