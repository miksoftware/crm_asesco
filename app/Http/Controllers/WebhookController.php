<?php

namespace App\Http\Controllers;

use App\Services\MessageService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        
        // ⭐ LOG COMPLETO PARA VERIFICAR CAMPOS DISPONIBLES
        Log::info('=== WEBHOOK RAW PAYLOAD ===', [
            'full_payload' => json_encode($payload, JSON_PRETTY_PRINT),
        ]);
        
        // Si es un mensaje, loguear los campos del key específicamente
        if (isset($payload['data']['key'])) {
            Log::info('=== WEBHOOK KEY FIELDS ===', [
                'remoteJid' => $payload['data']['key']['remoteJid'] ?? 'NO EXISTE',
                'senderPn' => $payload['data']['key']['senderPn'] ?? 'NO EXISTE',
                'cleanedSenderPn' => $payload['data']['key']['cleanedSenderPn'] ?? 'NO EXISTE',
                'senderLid' => $payload['data']['key']['senderLid'] ?? 'NO EXISTE',
                'addressingMode' => $payload['data']['key']['addressingMode'] ?? 'NO EXISTE',
                'participant' => $payload['data']['key']['participant'] ?? 'NO EXISTE',
            ]);
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
     * Process message status update webhook.
     */
    private function processMessageStatus(array $payload): JsonResponse
    {
        $data = $payload['data'] ?? [];
        $messageId = $data['key']['id'] ?? null;
        $status = $data['status'] ?? null;
        
        if (!$messageId || !$status) {
            return response()->json(['status' => 'skipped', 'reason' => 'missing_data']);
        }

        // Map Evolution API status to our status
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

        return response()->json(['status' => 'processed']);
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
