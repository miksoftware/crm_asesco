<?php

namespace Tests\Properties\Chat;

use App\Models\Channel;
use App\Models\Contact;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Property-Based Test: Unread Count Accuracy
 * 
 * Feature: chat-module, Property 4: Unread Count Accuracy
 * Validates: Requirements 2.3
 * 
 * For any conversation, the displayed unread count SHALL equal the actual 
 * count of messages where is_read = false and direction = 'incoming'.
 */
class UnreadCountPropertyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Property 4: Unread Count Accuracy
     * 
     * For any conversation, the displayed unread count SHALL equal the actual 
     * count of messages where is_read = false and direction = 'incoming'.
     * 
     * @test
     */
    public function unread_count_accuracy_property(): void
    {
        // Run 100 iterations with random data
        for ($i = 0; $i < 100; $i++) {
            $this->runUnreadCountIteration();
        }
    }

    private function runUnreadCountIteration(): void
    {
        // Create channel
        $channel = Channel::factory()->create(['is_active' => true]);
        
        // Create a contact
        $contact = Contact::factory()->create(['channel_id' => $channel->id]);
        
        // Create random number of messages (5-20) with varying read status and direction
        $messageCount = rand(5, 20);
        $expectedUnreadCount = 0;
        
        for ($j = 0; $j < $messageCount; $j++) {
            $direction = rand(0, 1) ? 'incoming' : 'outgoing';
            $isRead = (bool) rand(0, 1);
            
            // Count expected unread: incoming messages that are not read
            if ($direction === 'incoming' && !$isRead) {
                $expectedUnreadCount++;
            }
            
            Message::factory()->create([
                'contact_id' => $contact->id,
                'channel_id' => $channel->id,
                'direction' => $direction,
                'is_read' => $isRead,
                'sent_at' => now()->subMinutes(rand(1, 1000)),
            ]);
        }
        
        // Refresh the contact to get the computed unread_count
        $contact->refresh();
        
        // PROPERTY: The unread_count accessor must equal the actual count of 
        // incoming messages where is_read = false
        $actualUnreadCount = $contact->unread_count;
        
        // Also verify by direct query
        $directQueryCount = Message::where('contact_id', $contact->id)
            ->where('direction', 'incoming')
            ->where('is_read', false)
            ->count();
        
        $this->assertEquals(
            $expectedUnreadCount,
            $actualUnreadCount,
            "Unread count accessor ({$actualUnreadCount}) does not match expected count ({$expectedUnreadCount})"
        );
        
        $this->assertEquals(
            $directQueryCount,
            $actualUnreadCount,
            "Unread count accessor ({$actualUnreadCount}) does not match direct query count ({$directQueryCount})"
        );
        
        // Clean up for next iteration
        Message::query()->delete();
        Contact::query()->delete();
        Channel::query()->delete();
    }

    /**
     * Additional test: Verify that outgoing messages never count as unread
     * 
     * @test
     */
    public function outgoing_messages_never_count_as_unread(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $this->runOutgoingMessagesIteration();
        }
    }

    private function runOutgoingMessagesIteration(): void
    {
        // Create channel and contact
        $channel = Channel::factory()->create(['is_active' => true]);
        $contact = Contact::factory()->create(['channel_id' => $channel->id]);
        
        // Create only outgoing messages (some read, some not)
        $messageCount = rand(3, 10);
        
        for ($j = 0; $j < $messageCount; $j++) {
            Message::factory()->create([
                'contact_id' => $contact->id,
                'channel_id' => $channel->id,
                'direction' => 'outgoing',
                'is_read' => (bool) rand(0, 1), // Random read status
                'sent_at' => now()->subMinutes(rand(1, 1000)),
            ]);
        }
        
        $contact->refresh();
        
        // PROPERTY: Unread count should be 0 when all messages are outgoing
        $this->assertEquals(
            0,
            $contact->unread_count,
            "Unread count should be 0 when all messages are outgoing, got {$contact->unread_count}"
        );
        
        // Clean up
        Message::query()->delete();
        Contact::query()->delete();
        Channel::query()->delete();
    }

    /**
     * Additional test: Verify that read incoming messages don't count as unread
     * 
     * @test
     */
    public function read_incoming_messages_dont_count_as_unread(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $this->runReadIncomingMessagesIteration();
        }
    }

    private function runReadIncomingMessagesIteration(): void
    {
        // Create channel and contact
        $channel = Channel::factory()->create(['is_active' => true]);
        $contact = Contact::factory()->create(['channel_id' => $channel->id]);
        
        // Create only incoming messages that are all read
        $messageCount = rand(3, 10);
        
        for ($j = 0; $j < $messageCount; $j++) {
            Message::factory()->create([
                'contact_id' => $contact->id,
                'channel_id' => $channel->id,
                'direction' => 'incoming',
                'is_read' => true, // All read
                'sent_at' => now()->subMinutes(rand(1, 1000)),
            ]);
        }
        
        $contact->refresh();
        
        // PROPERTY: Unread count should be 0 when all incoming messages are read
        $this->assertEquals(
            0,
            $contact->unread_count,
            "Unread count should be 0 when all incoming messages are read, got {$contact->unread_count}"
        );
        
        // Clean up
        Message::query()->delete();
        Contact::query()->delete();
        Channel::query()->delete();
    }
}
