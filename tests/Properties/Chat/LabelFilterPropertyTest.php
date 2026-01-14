<?php

namespace Tests\Properties\Chat;

use App\Enums\ContactLabel;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Property-Based Test: Label Filter Correctness
 * 
 * Feature: chat-module, Property 14: Label Filter Correctness
 * Validates: Requirements 6.3
 * 
 * For any label filter applied, all returned conversations SHALL have 
 * contacts that contain that label in their labels array.
 */
class LabelFilterPropertyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Property 14: Label Filter Correctness
     * 
     * For any label filter applied, all returned conversations SHALL have 
     * contacts that contain that label in their labels array.
     * 
     * @test
     */
    public function label_filter_correctness_property(): void
    {
        // Run 100 iterations with random data
        for ($i = 0; $i < 100; $i++) {
            $this->runLabelFilterIteration();
        }
    }

    private function runLabelFilterIteration(): void
    {
        // Create channel
        $channel = Channel::factory()->create(['is_active' => true]);
        
        // Get all available labels
        $allLabels = ContactLabel::cases();
        
        // Create random number of contacts (5-15) with random labels
        $contactCount = rand(5, 15);
        $contacts = [];
        
        for ($j = 0; $j < $contactCount; $j++) {
            // Randomly assign 0-3 labels to each contact
            $labelCount = rand(0, 3);
            $contactLabels = [];
            
            if ($labelCount > 0) {
                $shuffledLabels = $allLabels;
                shuffle($shuffledLabels);
                $selectedLabels = array_slice($shuffledLabels, 0, $labelCount);
                $contactLabels = array_map(fn($label) => $label->value, $selectedLabels);
            }
            
            $contact = Contact::factory()->create([
                'channel_id' => $channel->id,
                'labels' => $contactLabels,
            ]);
            
            // Create at least one message for each contact
            Message::factory()->create([
                'contact_id' => $contact->id,
                'channel_id' => $channel->id,
                'sent_at' => now()->subMinutes(rand(1, 1000)),
            ]);
            
            $contacts[] = $contact;
        }
        
        // Pick a random label to filter by
        $filterLabel = $allLabels[array_rand($allLabels)];
        $filterLabelValue = $filterLabel->value;
        
        // Apply label filter (simulating the component logic)
        $filteredConversations = Contact::where('channel_id', $channel->id)
            ->whereHas('messages')
            ->whereJsonContains('labels', $filterLabelValue)
            ->get();
        
        // PROPERTY: All returned conversations must have the filter label in their labels array
        foreach ($filteredConversations as $contact) {
            $contactLabels = $contact->labels ?? [];
            
            $this->assertTrue(
                in_array($filterLabelValue, $contactLabels),
                "Contact {$contact->id} does not contain label '{$filterLabelValue}' in labels array. " .
                "Labels: " . json_encode($contactLabels)
            );
        }
        
        // Also verify completeness: all contacts with the label should be in results
        $expectedContacts = collect($contacts)->filter(function ($contact) use ($filterLabelValue) {
            $labels = $contact->labels ?? [];
            return in_array($filterLabelValue, $labels);
        });
        
        foreach ($expectedContacts as $expectedContact) {
            $this->assertTrue(
                $filteredConversations->contains('id', $expectedContact->id),
                "Contact {$expectedContact->id} with label '{$filterLabelValue}' should be in filtered results"
            );
        }
        
        // Clean up for next iteration
        Message::query()->delete();
        Contact::query()->delete();
        Channel::query()->delete();
    }
}
