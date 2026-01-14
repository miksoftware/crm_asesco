<?php

namespace Tests\Properties\Chat;

use App\Enums\ContactLabel;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Property-Based Test: Label Management Round-Trip
 * 
 * Feature: chat-module, Property 15: Label Management Round-Trip
 * Validates: Requirements 6.2, 6.4, 6.5
 * 
 * For any contact, adding a label then querying the contact SHALL return 
 * the contact with that label present, and removing a label then querying 
 * SHALL return the contact without that label.
 */
class LabelManagementRoundTripPropertyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Property 15: Label Management Round-Trip - Add Label
     * 
     * For any contact and any valid label, adding the label then querying 
     * the contact SHALL return the contact with that label present.
     * 
     * @test
     */
    public function label_add_round_trip_property(): void
    {
        // Run 100 iterations with random data
        for ($i = 0; $i < 100; $i++) {
            $this->runAddLabelIteration();
        }
    }

    /**
     * Property 15: Label Management Round-Trip - Remove Label
     * 
     * For any contact with labels, removing a label then querying 
     * the contact SHALL return the contact without that label.
     * 
     * @test
     */
    public function label_remove_round_trip_property(): void
    {
        // Run 100 iterations with random data
        for ($i = 0; $i < 100; $i++) {
            $this->runRemoveLabelIteration();
        }
    }

    /**
     * Property 15: Label Management Round-Trip - Multiple Operations
     * 
     * For any contact, a sequence of add/remove operations SHALL result
     * in the contact having exactly the expected labels.
     * 
     * @test
     */
    public function label_multiple_operations_round_trip_property(): void
    {
        // Run 100 iterations with random data
        for ($i = 0; $i < 100; $i++) {
            $this->runMultipleOperationsIteration();
        }
    }

    private function runAddLabelIteration(): void
    {
        // Create channel and contact
        $channel = Channel::factory()->create(['is_active' => true]);
        
        // Start with random initial labels (0-2 labels)
        $allLabels = ContactLabel::cases();
        $initialLabelCount = rand(0, 2);
        $initialLabels = [];
        
        if ($initialLabelCount > 0) {
            $shuffled = $allLabels;
            shuffle($shuffled);
            $selectedLabels = array_slice($shuffled, 0, $initialLabelCount);
            $initialLabels = array_map(fn($l) => $l->value, $selectedLabels);
        }
        
        $contact = Contact::factory()->create([
            'channel_id' => $channel->id,
            'labels' => $initialLabels,
        ]);
        
        // Pick a random label to add (that's not already present)
        $availableToAdd = array_values(array_filter($allLabels, function ($label) use ($initialLabels) {
            return !in_array($label->value, $initialLabels);
        }));
        
        if (empty($availableToAdd)) {
            // All labels already present, skip this iteration
            Contact::query()->delete();
            Channel::query()->delete();
            return;
        }
        
        $labelToAdd = $availableToAdd[array_rand($availableToAdd)];
        $labelValue = $labelToAdd->value;
        
        // Add the label (simulating the component logic)
        $currentLabels = $contact->labels ?? [];
        if (!in_array($labelValue, $currentLabels)) {
            $currentLabels[] = $labelValue;
            $contact->update(['labels' => $currentLabels]);
        }
        
        // Query the contact fresh from database
        $queriedContact = Contact::find($contact->id);
        $queriedLabels = $queriedContact->labels ?? [];
        
        // PROPERTY: The added label must be present in the queried contact
        $this->assertTrue(
            in_array($labelValue, $queriedLabels),
            "After adding label '{$labelValue}', it should be present in contact. " .
            "Initial labels: " . json_encode($initialLabels) . ", " .
            "Queried labels: " . json_encode($queriedLabels)
        );
        
        // Also verify all initial labels are still present
        foreach ($initialLabels as $initialLabel) {
            $this->assertTrue(
                in_array($initialLabel, $queriedLabels),
                "Initial label '{$initialLabel}' should still be present after adding new label"
            );
        }
        
        // Clean up for next iteration
        Contact::query()->delete();
        Channel::query()->delete();
    }

    private function runRemoveLabelIteration(): void
    {
        // Create channel and contact with at least one label
        $channel = Channel::factory()->create(['is_active' => true]);
        
        $allLabels = ContactLabel::cases();
        
        // Start with 1-4 random labels
        $initialLabelCount = rand(1, 4);
        $shuffled = $allLabels;
        shuffle($shuffled);
        $selectedLabels = array_slice($shuffled, 0, min($initialLabelCount, count($allLabels)));
        $initialLabels = array_map(fn($l) => $l->value, $selectedLabels);
        
        $contact = Contact::factory()->create([
            'channel_id' => $channel->id,
            'labels' => $initialLabels,
        ]);
        
        // Pick a random label to remove
        $labelToRemove = $initialLabels[array_rand($initialLabels)];
        
        // Remove the label (simulating the component logic)
        $currentLabels = $contact->labels ?? [];
        $currentLabels = array_values(array_filter($currentLabels, fn($l) => $l !== $labelToRemove));
        $contact->update(['labels' => $currentLabels]);
        
        // Query the contact fresh from database
        $queriedContact = Contact::find($contact->id);
        $queriedLabels = $queriedContact->labels ?? [];
        
        // PROPERTY: The removed label must NOT be present in the queried contact
        $this->assertFalse(
            in_array($labelToRemove, $queriedLabels),
            "After removing label '{$labelToRemove}', it should NOT be present in contact. " .
            "Initial labels: " . json_encode($initialLabels) . ", " .
            "Queried labels: " . json_encode($queriedLabels)
        );
        
        // Also verify other labels are still present
        $expectedRemainingLabels = array_filter($initialLabels, fn($l) => $l !== $labelToRemove);
        foreach ($expectedRemainingLabels as $remainingLabel) {
            $this->assertTrue(
                in_array($remainingLabel, $queriedLabels),
                "Label '{$remainingLabel}' should still be present after removing '{$labelToRemove}'"
            );
        }
        
        // Clean up for next iteration
        Contact::query()->delete();
        Channel::query()->delete();
    }

    private function runMultipleOperationsIteration(): void
    {
        // Create channel and contact
        $channel = Channel::factory()->create(['is_active' => true]);
        
        $allLabels = ContactLabel::cases();
        
        // Start with random initial labels (0-3)
        $initialLabelCount = rand(0, 3);
        $initialLabels = [];
        
        if ($initialLabelCount > 0) {
            $shuffled = $allLabels;
            shuffle($shuffled);
            $selectedLabels = array_slice($shuffled, 0, $initialLabelCount);
            $initialLabels = array_map(fn($l) => $l->value, $selectedLabels);
        }
        
        $contact = Contact::factory()->create([
            'channel_id' => $channel->id,
            'labels' => $initialLabels,
        ]);
        
        // Track expected labels
        $expectedLabels = $initialLabels;
        
        // Perform random sequence of add/remove operations (3-7 operations)
        $operationCount = rand(3, 7);
        
        for ($op = 0; $op < $operationCount; $op++) {
            $operation = rand(0, 1); // 0 = add, 1 = remove
            
            if ($operation === 0) {
                // Add operation
                $availableToAdd = array_values(array_filter($allLabels, function ($label) use ($expectedLabels) {
                    return !in_array($label->value, $expectedLabels);
                }));
                
                if (!empty($availableToAdd)) {
                    $labelToAdd = $availableToAdd[array_rand($availableToAdd)];
                    $labelValue = $labelToAdd->value;
                    
                    // Perform add
                    $currentLabels = $contact->fresh()->labels ?? [];
                    if (!in_array($labelValue, $currentLabels)) {
                        $currentLabels[] = $labelValue;
                        $contact->update(['labels' => $currentLabels]);
                        $expectedLabels[] = $labelValue;
                    }
                }
            } else {
                // Remove operation
                if (!empty($expectedLabels)) {
                    $labelToRemove = $expectedLabels[array_rand($expectedLabels)];
                    
                    // Perform remove
                    $currentLabels = $contact->fresh()->labels ?? [];
                    $currentLabels = array_values(array_filter($currentLabels, fn($l) => $l !== $labelToRemove));
                    $contact->update(['labels' => $currentLabels]);
                    $expectedLabels = array_values(array_filter($expectedLabels, fn($l) => $l !== $labelToRemove));
                }
            }
        }
        
        // Query the contact fresh from database
        $queriedContact = Contact::find($contact->id);
        $queriedLabels = $queriedContact->labels ?? [];
        
        // Sort both arrays for comparison
        sort($expectedLabels);
        sort($queriedLabels);
        
        // PROPERTY: After all operations, the contact should have exactly the expected labels
        $this->assertEquals(
            $expectedLabels,
            $queriedLabels,
            "After multiple operations, contact labels should match expected. " .
            "Expected: " . json_encode($expectedLabels) . ", " .
            "Actual: " . json_encode($queriedLabels)
        );
        
        // Clean up for next iteration
        Contact::query()->delete();
        Channel::query()->delete();
    }
}
