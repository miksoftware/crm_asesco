<?php

namespace Tests\Properties\Chat;

use App\Models\Channel;
use App\Models\Contact;
use App\Models\Message;
use App\Services\MessageService;
use App\Services\EvolutionApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Mockery;

/**
 * Property-Based Test: Sent Message Status Transition
 * 
 * Feature: chat-module, Property 9: Sent Message Status Transition
 * Validates: Requirements 4.2
 * 
 * For any message sent successfully via Evolution API, the message status 
 * SHALL be set to 'sent' and the message SHALL appear in the conversation.
 */
class SentMessageStatusPropertyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Property 9: Sent Message Status Transition
     * 
     * For any message sent successfully via Evolution API, the message status 
     * SHALL be set to 'sent' and the message SHALL appear in the conversation.
     * 
     * @test
     */
    public function sent_message_status_transition_property(): void
    {
        // Run 100 iterations with random data
        for ($i = 0; $i < 100; $i++) {
            $this->runSentMessageStatusIteration();
        }
    }

    private function runSentMessageStatusIteration(): void
    {
        // Create channel and contact
        $channel = Channel::factory()->create(['is_active' => true]);
        $contact = Contact::factory()->create(['channel_id' => $channel->id]);
        
        // Generate random message content
        $messageContent = $this->generateRandomMessageContent();
        
        // Mock Evolution API to return success
        $mockEvolutionApi = Mockery::mock(EvolutionApiService::class);
        $mockEvolutionApi->shouldReceive('sendTextMessage')
            ->once()
            ->andReturn([
                'success' => true,
                'data' => [
                    'key' => [
                        'id' => 'msg_' . uniqid(),
                    ],
                ],
            ]);
        
        $messageService = new MessageService($mockEvolutionApi);
        
        // Count messages before sending
        $messageCountBefore = Message::where('contact_id', $contact->id)
            ->where('channel_id', $channel->id)
            ->count();
        
        // Send message
        $message = $messageService->sendTextMessage(
            $channel->id,
            $contact->phone_number,
            $messageContent
        );
        
        // Count messages after sending
        $messageCountAfter = Message::where('contact_id', $contact->id)
            ->where('channel_id', $channel->id)
            ->count();
        
        // PROPERTY 1: Message count should increase by 1
        $this->assertEquals(
            $messageCountBefore + 1,
            $messageCountAfter,
            "Message count should increase by 1 after successful send"
        );
        
        // PROPERTY 2: Message status should be 'sent'
        $this->assertEquals(
            'sent',
            $message->status,
            "Message status should be 'sent' after successful API call"
        );
        
        // PROPERTY 3: Message should have correct content
        $this->assertEquals(
            $messageContent,
            $message->content,
            "Message content should match the sent content"
        );
        
        // PROPERTY 4: Message should be outgoing
        $this->assertEquals(
            'outgoing',
            $message->direction,
            "Message direction should be 'outgoing'"
        );
        
        // PROPERTY 5: Message should have a message_id from API
        $this->assertNotNull(
            $message->message_id,
            "Message should have a message_id from the API response"
        );
        
        // PROPERTY 6: Message should appear in conversation
        $conversationMessages = $messageService->getConversationMessages(
            $contact->id,
            $channel->id
        );
        
        $this->assertTrue(
            $conversationMessages->contains('id', $message->id),
            "Sent message should appear in the conversation"
        );
        
        // Clean up for next iteration
        Mockery::close();
        Message::query()->delete();
        Contact::query()->delete();
        Channel::query()->delete();
    }

    /**
     * Property 9 Extension: Failed message status
     * 
     * For any message that fails to send via Evolution API, the message status 
     * SHALL be set to 'failed'.
     * 
     * @test
     */
    public function failed_message_status_property(): void
    {
        // Run 100 iterations with random data
        for ($i = 0; $i < 100; $i++) {
            $this->runFailedMessageStatusIteration();
        }
    }

    private function runFailedMessageStatusIteration(): void
    {
        // Create channel and contact
        $channel = Channel::factory()->create(['is_active' => true]);
        $contact = Contact::factory()->create(['channel_id' => $channel->id]);
        
        // Generate random message content
        $messageContent = $this->generateRandomMessageContent();
        
        // Mock Evolution API to return failure
        $mockEvolutionApi = Mockery::mock(EvolutionApiService::class);
        $mockEvolutionApi->shouldReceive('sendTextMessage')
            ->once()
            ->andReturn([
                'success' => false,
                'error' => 'API Error: ' . $this->generateRandomErrorMessage(),
            ]);
        
        $messageService = new MessageService($mockEvolutionApi);
        
        // Send message (will fail)
        $message = $messageService->sendTextMessage(
            $channel->id,
            $contact->phone_number,
            $messageContent
        );
        
        // PROPERTY: Message status should be 'failed'
        $this->assertEquals(
            'failed',
            $message->status,
            "Message status should be 'failed' after API failure"
        );
        
        // PROPERTY: Message should still be created (for retry)
        $this->assertNotNull(
            $message->id,
            "Message should be created even on failure (for retry capability)"
        );
        
        // PROPERTY: Message should not have a message_id
        $this->assertNull(
            $message->message_id,
            "Failed message should not have a message_id"
        );
        
        // Clean up for next iteration
        Mockery::close();
        Message::query()->delete();
        Contact::query()->delete();
        Channel::query()->delete();
    }

    /**
     * Generate random message content.
     */
    private function generateRandomMessageContent(): string
    {
        $words = ['Hola', 'Buenos', 'días', 'tardes', 'noches', 'Gracias', 'Por', 'favor', 
                  'Confirmo', 'pago', 'mañana', 'hoy', 'semana', 'mes', 'Entendido', 
                  'De', 'acuerdo', 'Perfecto', 'Listo', 'Ok'];
        
        $wordCount = rand(1, 15);
        $message = [];
        
        for ($i = 0; $i < $wordCount; $i++) {
            $message[] = $words[array_rand($words)];
        }
        
        return implode(' ', $message);
    }

    /**
     * Generate random error message.
     */
    private function generateRandomErrorMessage(): string
    {
        $errors = [
            'Connection timeout',
            'Invalid phone number',
            'Instance not connected',
            'Rate limit exceeded',
            'Server error',
            'Authentication failed',
        ];
        
        return $errors[array_rand($errors)];
    }
}
