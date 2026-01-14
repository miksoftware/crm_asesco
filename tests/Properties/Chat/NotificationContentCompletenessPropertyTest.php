<?php

namespace Tests\Properties\Chat;

use App\Models\Channel;
use App\Models\ChatNotification;
use App\Models\Contact;
use App\Models\Message;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Property-Based Test: Notification Content Completeness
 * 
 * Feature: chat-module, Property 12: Notification Content Completeness
 * Validates: Requirements 5.2
 * 
 * For any notification displayed, the content SHALL include: 
 * contact name, channel name, and message preview.
 */
class NotificationContentCompletenessPropertyTest extends TestCase
{
    use RefreshDatabase;

    private NotificationService $notificationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->notificationService = new NotificationService();
    }

    /**
     * Property 12: Notification Content Completeness
     * 
     * For any notification displayed, the content SHALL include: 
     * contact name, channel name, and message preview.
     * 
     * @test
     */
    public function notification_content_completeness_property(): void
    {
        // Run 100 iterations with random data
        for ($i = 0; $i < 100; $i++) {
            $this->runNotificationContentCompletenessIteration();
        }
    }

    private function runNotificationContentCompletenessIteration(): void
    {
        // Create a user
        $user = User::factory()->create();
        
        // Create a channel with a name
        $channelName = fake()->company() . ' WhatsApp';
        $channel = Channel::factory()->create([
            'name' => $channelName,
        ]);
        $user->channels()->attach($channel->id);
        
        // Create a contact with a name
        $contactName = fake()->name();
        $contact = Contact::factory()->create([
            'channel_id' => $channel->id,
            'name' => $contactName,
        ]);
        
        // Create a message with content
        $messageContent = fake()->sentence(rand(3, 20));
        $message = Message::factory()->incoming()->create([
            'contact_id' => $contact->id,
            'channel_id' => $channel->id,
            'content' => $messageContent,
        ]);
        
        // Create notification using the service
        $notification = $this->notificationService->createMessageNotification($message);
        
        // PROPERTY: Notification title should contain contact name
        $this->assertNotEmpty(
            $notification->title,
            "Notification title should not be empty"
        );
        
        // PROPERTY: Notification body should contain message preview
        $this->assertNotEmpty(
            $notification->body,
            "Notification body should not be empty"
        );
        
        // PROPERTY: If message content is short, body should contain it
        if (strlen($messageContent) <= 100) {
            $this->assertEquals(
                $messageContent,
                $notification->body,
                "Notification body should equal message content for short messages"
            );
        } else {
            // For long messages, body should be truncated
            $this->assertStringStartsWith(
                substr($messageContent, 0, 50),
                $notification->body,
                "Notification body should start with message content for long messages"
            );
            $this->assertStringEndsWith(
                '...',
                $notification->body,
                "Truncated notification body should end with '...'"
            );
        }
        
        // Retrieve notification from database and verify relationships
        $storedNotification = ChatNotification::where('user_id', $user->id)
            ->where('message_id', $message->id)
            ->with(['contact', 'channel'])
            ->first();
        
        if ($storedNotification) {
            // PROPERTY: Notification should have associated contact
            $this->assertNotNull(
                $storedNotification->contact,
                "Notification should have an associated contact"
            );
            
            // PROPERTY: Notification should have associated channel
            $this->assertNotNull(
                $storedNotification->channel,
                "Notification should have an associated channel"
            );
            
            // PROPERTY: Channel name should be accessible
            $this->assertEquals(
                $channelName,
                $storedNotification->channel->name,
                "Notification channel name should match"
            );
            
            // PROPERTY: Contact name should be accessible
            $this->assertEquals(
                $contactName,
                $storedNotification->contact->name,
                "Notification contact name should match"
            );
        }
        
        // Clean up for next iteration
        ChatNotification::query()->delete();
        Message::query()->delete();
        Contact::query()->delete();
        $user->channels()->detach();
        Channel::query()->delete();
        User::query()->delete();
    }
}
