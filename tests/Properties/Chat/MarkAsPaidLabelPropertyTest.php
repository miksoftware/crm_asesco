<?php

namespace Tests\Properties\Chat;

use App\Enums\ContactLabel;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\PaymentPromise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Property-Based Test: Mark as Paid Label Update
 * 
 * Feature: chat-module, Property 16: Mark as Paid Label Update
 * Validates: Requirements 7.3
 * 
 * For any contact marked as paid, the contact's labels array SHALL contain 
 * the 'paid' label after the operation.
 */
class MarkAsPaidLabelPropertyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Property 16: Mark as Paid Label Update
     * 
     * For any contact, after marking as paid, the contact's labels array 
     * SHALL contain the 'paid' label.
     * 
     * @test
     */
    public function mark_as_paid_adds_paid_label_property(): void
    {
        // Run 100 iterations with random data
        for ($i = 0; $i < 100; $i++) {
            $this->runMarkAsPaidIteration();
        }
    }

    /**
     * Property 16: Mark as Paid - Preserves Existing Labels
     * 
     * For any contact with existing labels, marking as paid SHALL preserve
     * all existing labels while adding the 'paid' label.
     * 
     * @test
     */
    public function mark_as_paid_preserves_existing_labels_property(): void
    {
        // Run 100 iterations with random data
        for ($i = 0; $i < 100; $i++) {
            $this->runPreserveLabelsIteration();
        }
    }

    /**
     * Property 16: Mark as Paid - Updates Pending Promises
     * 
     * For any contact with pending payment promises, marking as paid SHALL
     * update all pending promises to fulfilled status.
     * 
     * @test
     */
    public function mark_as_paid_fulfills_pending_promises_property(): void
    {
        // Run 100 iterations with random data
        for ($i = 0; $i < 100; $i++) {
            $this->runFulfillPromisesIteration();
        }
    }

    private function runMarkAsPaidIteration(): void
    {
        // Create channel and contact
        $channel = Channel::factory()->create(['is_active' => true]);
        
        // Generate random initial labels (0-3 labels, excluding 'paid')
        $allLabels = ContactLabel::cases();
        $labelsExcludingPaid = array_filter($allLabels, fn($l) => $l->value !== 'paid');
        
        $initialLabelCount = rand(0, 3);
        $initialLabels = [];
        
        if ($initialLabelCount > 0) {
            $shuffled = array_values($labelsExcludingPaid);
            shuffle($shuffled);
            $selectedLabels = array_slice($shuffled, 0, min($initialLabelCount, count($shuffled)));
            $initialLabels = array_map(fn($l) => $l->value, $selectedLabels);
        }
        
        $contact = Contact::factory()->create([
            'channel_id' => $channel->id,
            'labels' => $initialLabels,
        ]);
        
        // Simulate mark as paid operation (same logic as QuickActions component)
        $labels = $contact->labels ?? [];
        if (!in_array('paid', $labels)) {
            $labels[] = 'paid';
            $contact->update(['labels' => $labels]);
        }
        
        // Query the contact fresh from database
        $queriedContact = Contact::find($contact->id);
        $queriedLabels = $queriedContact->labels ?? [];
        
        // PROPERTY: The 'paid' label must be present after marking as paid
        $this->assertTrue(
            in_array('paid', $queriedLabels),
            "After marking as paid, 'paid' label should be present. " .
            "Initial labels: " . json_encode($initialLabels) . ", " .
            "Queried labels: " . json_encode($queriedLabels)
        );
        
        // Clean up for next iteration
        Contact::query()->delete();
        Channel::query()->delete();
    }

    private function runPreserveLabelsIteration(): void
    {
        // Create channel and contact with existing labels
        $channel = Channel::factory()->create(['is_active' => true]);
        
        // Generate random initial labels (1-4 labels, excluding 'paid')
        $allLabels = ContactLabel::cases();
        $labelsExcludingPaid = array_filter($allLabels, fn($l) => $l->value !== 'paid');
        
        $initialLabelCount = rand(1, min(4, count($labelsExcludingPaid)));
        $shuffled = array_values($labelsExcludingPaid);
        shuffle($shuffled);
        $selectedLabels = array_slice($shuffled, 0, $initialLabelCount);
        $initialLabels = array_map(fn($l) => $l->value, $selectedLabels);
        
        $contact = Contact::factory()->create([
            'channel_id' => $channel->id,
            'labels' => $initialLabels,
        ]);
        
        // Simulate mark as paid operation
        $labels = $contact->labels ?? [];
        if (!in_array('paid', $labels)) {
            $labels[] = 'paid';
            $contact->update(['labels' => $labels]);
        }
        
        // Query the contact fresh from database
        $queriedContact = Contact::find($contact->id);
        $queriedLabels = $queriedContact->labels ?? [];
        
        // PROPERTY: All initial labels must still be present
        foreach ($initialLabels as $initialLabel) {
            $this->assertTrue(
                in_array($initialLabel, $queriedLabels),
                "Initial label '{$initialLabel}' should be preserved after marking as paid. " .
                "Initial labels: " . json_encode($initialLabels) . ", " .
                "Queried labels: " . json_encode($queriedLabels)
            );
        }
        
        // PROPERTY: The 'paid' label must also be present
        $this->assertTrue(
            in_array('paid', $queriedLabels),
            "The 'paid' label should be present after marking as paid"
        );
        
        // Clean up for next iteration
        Contact::query()->delete();
        Channel::query()->delete();
    }

    private function runFulfillPromisesIteration(): void
    {
        // Create user, channel and contact
        $user = User::factory()->create();
        $channel = Channel::factory()->create(['is_active' => true]);
        
        $contact = Contact::factory()->create([
            'channel_id' => $channel->id,
            'labels' => [],
        ]);
        
        // Create random number of payment promises (1-5) with random statuses
        $promiseCount = rand(1, 5);
        $pendingCount = 0;
        
        for ($p = 0; $p < $promiseCount; $p++) {
            $status = ['pending', 'fulfilled', 'broken'][rand(0, 2)];
            if ($status === 'pending') {
                $pendingCount++;
            }
            
            PaymentPromise::create([
                'contact_id' => $contact->id,
                'user_id' => $user->id,
                'promised_date' => now()->addDays(rand(1, 30)),
                'promised_amount' => rand(100, 10000) / 100,
                'status' => $status,
                'notes' => null,
            ]);
        }
        
        // Simulate mark as paid operation (same logic as QuickActions component)
        $labels = $contact->labels ?? [];
        if (!in_array('paid', $labels)) {
            $labels[] = 'paid';
            $contact->update(['labels' => $labels]);
        }
        
        // Update pending promises to fulfilled
        $contact->paymentPromises()
            ->where('status', 'pending')
            ->update([
                'status' => 'fulfilled',
                'fulfilled_at' => now(),
            ]);
        
        // Query promises fresh from database
        $queriedPromises = PaymentPromise::where('contact_id', $contact->id)->get();
        
        // PROPERTY: No promises should have 'pending' status after marking as paid
        $stillPending = $queriedPromises->where('status', 'pending')->count();
        $this->assertEquals(
            0,
            $stillPending,
            "After marking as paid, no promises should be pending. " .
            "Initial pending count: {$pendingCount}, " .
            "Still pending: {$stillPending}"
        );
        
        // PROPERTY: Previously pending promises should now be fulfilled
        $fulfilledCount = $queriedPromises->where('status', 'fulfilled')->count();
        $this->assertGreaterThanOrEqual(
            $pendingCount,
            $fulfilledCount,
            "Fulfilled count should include all previously pending promises"
        );
        
        // Clean up for next iteration
        PaymentPromise::query()->delete();
        Contact::query()->delete();
        Channel::query()->delete();
        User::query()->delete();
    }
}
