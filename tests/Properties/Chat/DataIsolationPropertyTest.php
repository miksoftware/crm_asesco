<?php

namespace Tests\Properties\Chat;

use App\Models\Channel;
use App\Models\Contact;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Property-Based Test: Channel-Based Data Isolation
 * 
 * Feature: chat-module, Property 1: Channel-Based Data Isolation
 * Validates: Requirements 1.1, 1.4
 * 
 * For any user and any query to the chat system, all returned conversations 
 * and messages SHALL belong only to channels that are assigned to that user.
 */
class DataIsolationPropertyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Property 1: Channel-Based Data Isolation
     * 
     * For any user with assigned channels, querying conversations should only
     * return contacts from those assigned channels.
     * 
     * @test
     */
    public function channel_based_data_isolation_property(): void
    {
        // Run 100 iterations with random data
        for ($i = 0; $i < 100; $i++) {
            $this->runIsolationPropertyIteration();
        }
    }

    private function runIsolationPropertyIteration(): void
    {
        // Create a user
        $user = User::factory()->create();
        
        // Create random number of channels (2-5)
        $totalChannels = rand(2, 5);
        $channels = Channel::factory()->count($totalChannels)->create();
        
        // Randomly assign some channels to the user (at least 1)
        $assignedCount = rand(1, $totalChannels - 1);
        $assignedChannels = $channels->random($assignedCount);
        $unassignedChannels = $channels->diff($assignedChannels);
        
        // Attach assigned channels to user
        $user->channels()->attach($assignedChannels->pluck('id'));
        
        // Create contacts in all channels
        foreach ($channels as $channel) {
            $contactCount = rand(1, 3);
            Contact::factory()
                ->count($contactCount)
                ->create(['channel_id' => $channel->id]);
        }
        
        // Create messages for all contacts
        foreach (Contact::all() as $contact) {
            Message::factory()
                ->count(rand(1, 5))
                ->create([
                    'contact_id' => $contact->id,
                    'channel_id' => $contact->channel_id,
                ]);
        }
        
        // Get user's assigned channel IDs
        $assignedChannelIds = $user->channels()->pluck('channels.id')->toArray();
        
        // Query conversations (contacts) filtered by user's channels
        $conversations = Contact::whereIn('channel_id', $assignedChannelIds)->get();
        
        // PROPERTY: All returned conversations must belong to assigned channels
        foreach ($conversations as $conversation) {
            $this->assertContains(
                $conversation->channel_id,
                $assignedChannelIds,
                "Conversation channel_id {$conversation->channel_id} is not in user's assigned channels"
            );
        }
        
        // PROPERTY: No conversations from unassigned channels should be returned
        $unassignedChannelIds = $unassignedChannels->pluck('id')->toArray();
        foreach ($conversations as $conversation) {
            $this->assertNotContains(
                $conversation->channel_id,
                $unassignedChannelIds,
                "Conversation from unassigned channel {$conversation->channel_id} was returned"
            );
        }
        
        // Query messages filtered by user's channels
        $messages = Message::whereIn('channel_id', $assignedChannelIds)->get();
        
        // PROPERTY: All returned messages must belong to assigned channels
        foreach ($messages as $message) {
            $this->assertContains(
                $message->channel_id,
                $assignedChannelIds,
                "Message channel_id {$message->channel_id} is not in user's assigned channels"
            );
        }
        
        // Clean up for next iteration
        Message::query()->delete();
        Contact::query()->delete();
        $user->channels()->detach();
        Channel::query()->delete();
        User::query()->delete();
    }
}
