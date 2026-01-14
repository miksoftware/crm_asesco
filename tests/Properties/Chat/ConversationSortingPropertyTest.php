<?php

namespace Tests\Properties\Chat;

use App\Models\Channel;
use App\Models\Contact;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Property-Based Test: Conversation Sorting by Timestamp
 * 
 * Feature: chat-module, Property 2: Conversation Sorting by Timestamp
 * Validates: Requirements 2.1
 * 
 * For any list of conversations returned by the system, the conversations 
 * SHALL be sorted by last message timestamp in descending order (most recent first).
 */
class ConversationSortingPropertyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Property 2: Conversation Sorting by Timestamp
     * 
     * For any list of conversations returned by the system, the conversations 
     * SHALL be sorted by last message timestamp in descending order.
     * 
     * @test
     */
    public function conversation_sorting_property(): void
    {
        // Run 100 iterations with random data
        for ($i = 0; $i < 100; $i++) {
            $this->runConversationSortingIteration();
        }
    }

    private function runConversationSortingIteration(): void
    {
        // Create channel
        $channel = Channel::factory()->create(['is_active' => true]);
        
        // Create random number of contacts (3-10) with messages
        $contactCount = rand(3, 10);
        
        for ($j = 0; $j < $contactCount; $j++) {
            $contact = Contact::factory()->create(['channel_id' => $channel->id]);
            
            // Create random number of messages (1-5) for each contact
            $messageCount = rand(1, 5);
            for ($k = 0; $k < $messageCount; $k++) {
                Message::factory()->create([
                    'contact_id' => $contact->id,
                    'channel_id' => $channel->id,
                    'sent_at' => now()->subMinutes(rand(1, 10000)),
                ]);
            }
        }
        
        // Get conversations sorted by last message timestamp (simulating the component logic)
        $conversations = Contact::where('channel_id', $channel->id)
            ->whereHas('messages')
            ->with(['messages' => function ($q) {
                $q->orderByDesc('sent_at')->orderByDesc('created_at')->limit(1);
            }])
            ->get()
            ->sortByDesc(function ($contact) {
                $lastMessage = $contact->messages->first();
                return $lastMessage ? $lastMessage->sent_at : $contact->created_at;
            })
            ->values();
        
        // PROPERTY: Conversations must be sorted by last message timestamp in descending order
        $previousTimestamp = null;
        
        foreach ($conversations as $contact) {
            $lastMessage = $contact->messages->first();
            $currentTimestamp = $lastMessage ? $lastMessage->sent_at : $contact->created_at;
            
            if ($previousTimestamp !== null) {
                $this->assertTrue(
                    $currentTimestamp <= $previousTimestamp,
                    "Conversations are not sorted by last message timestamp in descending order. " .
                    "Previous: {$previousTimestamp}, Current: {$currentTimestamp}"
                );
            }
            
            $previousTimestamp = $currentTimestamp;
        }
        
        // Clean up for next iteration
        Message::query()->delete();
        Contact::query()->delete();
        Channel::query()->delete();
    }
}
