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
 * Property-Based Test: Notification Count Invariant
 * 
 * Feature: chat-module, Property 11: Notification Count Invariant
 * Validates: Requirements 5.1, 5.4
 * 
 * For any sequence of message arrivals and conversation reads, the notification 
 * count SHALL equal the total unread messages across all assigned channels 
 * minus the messages that have been read.
 */
class NotificationCountPropertyTest extends TestCase
{
    use RefreshDatabase;

    private NotificationService $notificationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->notificationService = new NotificationService();
    }

    /**
     * Property 11: Notification Count Invariant
     * 
     * For any sequence of message arrivals and conversation reads, the notification 
     * count SHALL equal the total unread notifications for the user.
     * 
     * @test
     */
    public function notification_count_invariant_property(): void
    {
        // Run 100 iterations with random data
        for ($i = 0; $i < 100; $i++) {
            $this->runNotificationCountInvariantIteration();
        }
    }

    private function runNotificationCountInvariantIteration(): void
    {
        // Create a user
        $user = User::factory()->create();
        
        // Create channels and assign to user
        $channelCount = rand(1, 3);
        $channels = Channel::factory()->count($channelCount)->create();
        $user->channels()->attach($channels->pluck('id'));
        
        // Create contacts in each channel
        $contacts = collect();
        foreach ($channels as $channel) {
            $contactCount = rand(1, 3);
            $channelContacts = Contact::factory()
                ->count($contactCount)
                ->create(['channel_id' => $channel->id]);
            $contacts = $contacts->merge($channelContacts);
        }
        
        // Create messages and notifications
        $totalNotifications = 0;
        foreach ($contacts as $contact) {
            $messageCount = rand(1, 5);
            for ($j = 0; $j < $messageCount; $j++) {
                $message = Message::factory()->incoming()->create([
                    'contact_id' => $contact->id,
                    'channel_id' => $contact->channel_id,
                ]);
                
                // Create notification for the user
                ChatNotification::factory()->unread()->create([
                    'user_id' => $user->id,
                    'contact_id' => $contact->id,
                    'channel_id' => $contact->channel_id,
                    'message_id' => $message->id,
                ]);
                $totalNotifications++;
            }
        }
        
        // PROPERTY: Initial unread count should equal total notifications
        $unreadCount = $this->notificationService->getUnreadCount($user->id);
        $this->assertEquals(
            $totalNotifications,
            $unreadCount,
            "Initial unread count {$unreadCount} does not equal total notifications {$totalNotifications}"
        );
        
        // Randomly mark some notifications as read
        $notificationsToRead = rand(0, $totalNotifications);
        $notificationIds = ChatNotification::where('user_id', $user->id)
            ->where('is_read', false)
            ->take($notificationsToRead)
            ->pluck('id');
        
        foreach ($notificationIds as $notificationId) {
            $this->notificationService->markAsRead($notificationId);
        }
        
        // PROPERTY: Unread count should equal total minus read
        $expectedUnread = $totalNotifications - $notificationsToRead;
        $actualUnread = $this->notificationService->getUnreadCount($user->id);
        
        $this->assertEquals(
            $expectedUnread,
            $actualUnread,
            "After marking {$notificationsToRead} as read, expected {$expectedUnread} unread but got {$actualUnread}"
        );
        
        // PROPERTY: Database count should match service count
        $dbUnreadCount = ChatNotification::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();
        
        $this->assertEquals(
            $dbUnreadCount,
            $actualUnread,
            "Service unread count {$actualUnread} does not match database count {$dbUnreadCount}"
        );
        
        // Clean up for next iteration
        ChatNotification::query()->delete();
        Message::query()->delete();
        Contact::query()->delete();
        $user->channels()->detach();
        Channel::query()->delete();
        User::query()->delete();
    }
}
