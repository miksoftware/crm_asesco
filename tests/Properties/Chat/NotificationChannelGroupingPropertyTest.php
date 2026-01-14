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
 * Property-Based Test: Notification Channel Grouping
 * 
 * Feature: chat-module, Property 13: Notification Channel Grouping
 * Validates: Requirements 5.5
 * 
 * For any user with multiple channels, notifications SHALL be grouped 
 * by channel when displayed.
 */
class NotificationChannelGroupingPropertyTest extends TestCase
{
    use RefreshDatabase;

    private NotificationService $notificationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->notificationService = new NotificationService();
    }

    /**
     * Property 13: Notification Channel Grouping
     * 
     * For any user with multiple channels, notifications SHALL be grouped 
     * by channel when displayed.
     * 
     * @test
     */
    public function notification_channel_grouping_property(): void
    {
        // Run 100 iterations with random data
        for ($i = 0; $i < 100; $i++) {
            $this->runNotificationChannelGroupingIteration();
        }
    }

    private function runNotificationChannelGroupingIteration(): void
    {
        // Create a user
        $user = User::factory()->create();
        
        // Create multiple channels (2-4)
        $channelCount = rand(2, 4);
        $channels = Channel::factory()->count($channelCount)->create();
        $user->channels()->attach($channels->pluck('id'));
        
        // Track expected notifications per channel
        $expectedNotificationsPerChannel = [];
        
        // Create contacts and notifications for each channel
        foreach ($channels as $channel) {
            $contactCount = rand(1, 3);
            $contacts = Contact::factory()
                ->count($contactCount)
                ->create(['channel_id' => $channel->id]);
            
            $channelNotificationCount = 0;
            
            foreach ($contacts as $contact) {
                $messageCount = rand(1, 3);
                for ($j = 0; $j < $messageCount; $j++) {
                    $message = Message::factory()->incoming()->create([
                        'contact_id' => $contact->id,
                        'channel_id' => $channel->id,
                    ]);
                    
                    // Create notification for the user
                    ChatNotification::factory()->unread()->create([
                        'user_id' => $user->id,
                        'contact_id' => $contact->id,
                        'channel_id' => $channel->id,
                        'message_id' => $message->id,
                    ]);
                    $channelNotificationCount++;
                }
            }
            
            $expectedNotificationsPerChannel[$channel->id] = $channelNotificationCount;
        }
        
        // Get grouped notifications
        $groupedNotifications = $this->notificationService->getNotificationsGroupedByChannel($user->id, 100);
        
        // PROPERTY: Grouped notifications should be a collection
        $this->assertInstanceOf(
            \Illuminate\Support\Collection::class,
            $groupedNotifications,
            "Grouped notifications should be a Collection"
        );
        
        // PROPERTY: Each group key should be a channel ID
        foreach ($groupedNotifications->keys() as $channelId) {
            $this->assertTrue(
                $channels->pluck('id')->contains($channelId),
                "Group key {$channelId} should be a valid channel ID"
            );
        }
        
        // PROPERTY: All notifications in a group should belong to the same channel
        foreach ($groupedNotifications as $channelId => $notifications) {
            foreach ($notifications as $notification) {
                $this->assertEquals(
                    $channelId,
                    $notification->channel_id,
                    "Notification channel_id {$notification->channel_id} should match group key {$channelId}"
                );
            }
        }
        
        // PROPERTY: Total notifications across all groups should match expected
        $totalGroupedNotifications = $groupedNotifications->flatten()->count();
        $totalExpected = array_sum($expectedNotificationsPerChannel);
        
        $this->assertEquals(
            $totalExpected,
            $totalGroupedNotifications,
            "Total grouped notifications {$totalGroupedNotifications} should equal expected {$totalExpected}"
        );
        
        // PROPERTY: Each channel group should have the expected count
        foreach ($expectedNotificationsPerChannel as $channelId => $expectedCount) {
            $actualCount = $groupedNotifications->get($channelId)?->count() ?? 0;
            $this->assertEquals(
                $expectedCount,
                $actualCount,
                "Channel {$channelId} should have {$expectedCount} notifications but has {$actualCount}"
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

    /**
     * Property: Single channel user should have one group
     * 
     * @test
     */
    public function single_channel_user_has_one_group_property(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $this->runSingleChannelGroupingIteration();
        }
    }

    private function runSingleChannelGroupingIteration(): void
    {
        // Create a user with single channel
        $user = User::factory()->create();
        $channel = Channel::factory()->create();
        $user->channels()->attach($channel->id);
        
        // Create contacts and notifications
        $contactCount = rand(1, 3);
        $contacts = Contact::factory()
            ->count($contactCount)
            ->create(['channel_id' => $channel->id]);
        
        $notificationCount = 0;
        foreach ($contacts as $contact) {
            $messageCount = rand(1, 3);
            for ($j = 0; $j < $messageCount; $j++) {
                $message = Message::factory()->incoming()->create([
                    'contact_id' => $contact->id,
                    'channel_id' => $channel->id,
                ]);
                
                ChatNotification::factory()->unread()->create([
                    'user_id' => $user->id,
                    'contact_id' => $contact->id,
                    'channel_id' => $channel->id,
                    'message_id' => $message->id,
                ]);
                $notificationCount++;
            }
        }
        
        // Get grouped notifications
        $groupedNotifications = $this->notificationService->getNotificationsGroupedByChannel($user->id, 100);
        
        // PROPERTY: Should have exactly one group
        $this->assertEquals(
            1,
            $groupedNotifications->count(),
            "Single channel user should have exactly one notification group"
        );
        
        // PROPERTY: The group should be keyed by the channel ID
        $this->assertTrue(
            $groupedNotifications->has($channel->id),
            "Group should be keyed by channel ID {$channel->id}"
        );
        
        // PROPERTY: Group should contain all notifications
        $this->assertEquals(
            $notificationCount,
            $groupedNotifications->get($channel->id)->count(),
            "Group should contain all {$notificationCount} notifications"
        );
        
        // Clean up
        ChatNotification::query()->delete();
        Message::query()->delete();
        Contact::query()->delete();
        $user->channels()->detach();
        Channel::query()->delete();
        User::query()->delete();
    }
}
