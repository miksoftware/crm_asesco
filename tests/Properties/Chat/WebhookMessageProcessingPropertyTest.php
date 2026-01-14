<?php

namespace Tests\Properties\Chat;

use App\Models\Channel;
use App\Models\Contact;
use App\Models\Message;
use App\Services\MessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Property-Based Test: Webhook Message Processing
 * 
 * Feature: chat-module, Property 21: Webhook Message Processing
 * Validates: Requirements 9.1
 * 
 * For any valid webhook payload from Evolution API containing a new message,
 * the system SHALL create a Message record with correct contact_id, channel_id,
 * content, and direction.
 */
class WebhookMessageProcessingPropertyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Property 21: Webhook Message Processing
     * 
     * For any valid webhook payload, processing it should create a message
     * with the correct attributes.
     * 
     * @test
     */
    public function webhook_message_processing_property(): void
    {
        // Run 100 iterations with random data
        for ($i = 0; $i < 100; $i++) {
            $this->runWebhookProcessingPropertyIteration();
        }
    }

    private function runWebhookProcessingPropertyIteration(): void
    {
        // Create a channel with a random instance name
        $instanceName = 'instance_' . fake()->uuid();
        $channel = Channel::factory()->create([
            'instance_name' => $instanceName,
            'status' => 'connected',
            'is_active' => true,
        ]);

        // Generate random phone number (Colombian format)
        $phoneNumber = '57' . fake()->numerify('3#########');
        
        // Generate random message content
        $messageContent = fake()->sentence(rand(3, 15));
        
        // Generate random message ID
        $messageId = fake()->uuid();
        
        // Generate random push name
        $pushName = fake()->name();

        // Build webhook payload (Evolution API format)
        $webhookPayload = [
            'event' => 'messages.upsert',
            'instance' => $instanceName,
            'data' => [
                'key' => [
                    'remoteJid' => $phoneNumber . '@s.whatsapp.net',
                    'fromMe' => false,
                    'id' => $messageId,
                ],
                'pushName' => $pushName,
                'message' => [
                    'conversation' => $messageContent,
                ],
                'messageTimestamp' => time(),
            ],
        ];

        // Process the webhook
        $messageService = app(MessageService::class);
        $message = $messageService->processIncomingMessage($webhookPayload);

        // PROPERTY: Message should be created with correct channel_id
        $this->assertEquals(
            $channel->id,
            $message->channel_id,
            "Message channel_id should match the channel found by instance name"
        );

        // PROPERTY: Message should have correct direction
        $this->assertEquals(
            'incoming',
            $message->direction,
            "Message direction should be 'incoming' for webhook messages"
        );

        // PROPERTY: Message should have correct content
        $this->assertEquals(
            $messageContent,
            $message->content,
            "Message content should match the webhook payload content"
        );

        // PROPERTY: Message should have correct message_id from Evolution API
        $this->assertEquals(
            $messageId,
            $message->message_id,
            "Message message_id should match the Evolution API message ID"
        );

        // PROPERTY: Contact should be created/found with correct channel_id
        $this->assertEquals(
            $channel->id,
            $message->contact->channel_id,
            "Contact channel_id should match the message channel_id"
        );

        // PROPERTY: Contact should have correct phone number
        $this->assertEquals(
            $phoneNumber,
            $message->contact->phone_number,
            "Contact phone_number should be extracted from remoteJid"
        );

        // PROPERTY: Message should be marked as unread
        $this->assertFalse(
            $message->is_read,
            "Incoming message should be marked as unread"
        );

        // PROPERTY: Message type should be 'text' for conversation messages
        $this->assertEquals(
            'text',
            $message->type,
            "Message type should be 'text' for conversation messages"
        );

        // Clean up for next iteration
        Message::query()->delete();
        Contact::query()->delete();
        Channel::query()->delete();
    }

    /**
     * Property: Duplicate messages should not be created
     * 
     * @test
     */
    public function duplicate_messages_are_not_created_property(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $this->runDuplicateMessagePropertyIteration();
        }
    }

    private function runDuplicateMessagePropertyIteration(): void
    {
        // Create a channel
        $instanceName = 'instance_' . fake()->uuid();
        $channel = Channel::factory()->create([
            'instance_name' => $instanceName,
            'status' => 'connected',
            'is_active' => true,
        ]);

        $phoneNumber = '57' . fake()->numerify('3#########');
        $messageContent = fake()->sentence();
        $messageId = fake()->uuid();

        $webhookPayload = [
            'event' => 'messages.upsert',
            'instance' => $instanceName,
            'data' => [
                'key' => [
                    'remoteJid' => $phoneNumber . '@s.whatsapp.net',
                    'fromMe' => false,
                    'id' => $messageId,
                ],
                'pushName' => fake()->name(),
                'message' => [
                    'conversation' => $messageContent,
                ],
                'messageTimestamp' => time(),
            ],
        ];

        $messageService = app(MessageService::class);

        // Process the same webhook twice
        $message1 = $messageService->processIncomingMessage($webhookPayload);
        $message2 = $messageService->processIncomingMessage($webhookPayload);

        // PROPERTY: Both calls should return the same message (no duplicate)
        $this->assertEquals(
            $message1->id,
            $message2->id,
            "Processing the same webhook twice should return the same message"
        );

        // PROPERTY: Only one message should exist with this message_id
        $messageCount = Message::where('message_id', $messageId)
            ->where('channel_id', $channel->id)
            ->count();
        
        $this->assertEquals(
            1,
            $messageCount,
            "Only one message should exist for a given message_id and channel"
        );

        // Clean up
        Message::query()->delete();
        Contact::query()->delete();
        Channel::query()->delete();
    }

    /**
     * Property: Different message types should be correctly identified
     * 
     * @test
     */
    public function message_type_identification_property(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $this->runMessageTypePropertyIteration();
        }
    }

    private function runMessageTypePropertyIteration(): void
    {
        $instanceName = 'instance_' . fake()->uuid();
        $channel = Channel::factory()->create([
            'instance_name' => $instanceName,
            'status' => 'connected',
            'is_active' => true,
        ]);

        $phoneNumber = '57' . fake()->numerify('3#########');
        $messageId = fake()->uuid();

        // Randomly select a message type
        $messageTypes = [
            'text' => ['conversation' => fake()->sentence()],
            'image' => ['imageMessage' => ['url' => 'https://example.com/image.jpg', 'caption' => fake()->sentence()]],
            'document' => ['documentMessage' => ['url' => 'https://example.com/doc.pdf', 'fileName' => 'document.pdf']],
            'audio' => ['audioMessage' => ['url' => 'https://example.com/audio.ogg']],
            'video' => ['videoMessage' => ['url' => 'https://example.com/video.mp4', 'caption' => fake()->sentence()]],
        ];

        $expectedType = array_rand($messageTypes);
        $messageData = $messageTypes[$expectedType];

        $webhookPayload = [
            'event' => 'messages.upsert',
            'instance' => $instanceName,
            'data' => [
                'key' => [
                    'remoteJid' => $phoneNumber . '@s.whatsapp.net',
                    'fromMe' => false,
                    'id' => $messageId,
                ],
                'pushName' => fake()->name(),
                'message' => $messageData,
                'messageTimestamp' => time(),
            ],
        ];

        $messageService = app(MessageService::class);
        $message = $messageService->processIncomingMessage($webhookPayload);

        // PROPERTY: Message type should match the expected type
        $this->assertEquals(
            $expectedType,
            $message->type,
            "Message type should be correctly identified as '{$expectedType}'"
        );

        // Clean up
        Message::query()->delete();
        Contact::query()->delete();
        Channel::query()->delete();
    }
}
