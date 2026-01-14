<?php

namespace Tests\Properties\Chat;

use App\Models\Channel;
use App\Models\Contact;
use App\Models\FollowUp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Property-Based Test: Follow-Up Creation Persistence
 * 
 * Feature: chat-module, Property 17: Follow-Up Creation Persistence
 * Validates: Requirements 7.4
 * 
 * For any follow-up scheduled for a contact, querying the contact's follow-ups 
 * SHALL return the scheduled follow-up with correct date and note.
 */
class FollowUpCreationPropertyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Property 17: Follow-Up Creation Persistence
     * 
     * For any follow-up scheduled for a contact, querying the contact's follow-ups 
     * SHALL return the scheduled follow-up with correct date and note.
     * 
     * @test
     */
    public function follow_up_creation_persistence_property(): void
    {
        // Run 100 iterations with random data
        for ($i = 0; $i < 100; $i++) {
            $this->runFollowUpCreationIteration();
        }
    }

    /**
     * Property 17: Follow-Up Creation - Multiple Follow-ups
     * 
     * For any contact with multiple follow-ups, all follow-ups SHALL be 
     * retrievable with their correct data.
     * 
     * @test
     */
    public function multiple_follow_ups_persistence_property(): void
    {
        // Run 100 iterations with random data
        for ($i = 0; $i < 100; $i++) {
            $this->runMultipleFollowUpsIteration();
        }
    }

    /**
     * Property 17: Follow-Up Creation - Status Persistence
     * 
     * For any follow-up, the initial status SHALL be 'pending' and 
     * SHALL be correctly persisted.
     * 
     * @test
     */
    public function follow_up_status_persistence_property(): void
    {
        // Run 100 iterations with random data
        for ($i = 0; $i < 100; $i++) {
            $this->runStatusPersistenceIteration();
        }
    }

    private function runFollowUpCreationIteration(): void
    {
        // Create user, channel and contact
        $user = User::factory()->create();
        $channel = Channel::factory()->create(['is_active' => true]);
        
        $contact = Contact::factory()->create([
            'channel_id' => $channel->id,
        ]);
        
        // Generate random follow-up data
        $scheduledDate = now()->addDays(rand(1, 30))->addHours(rand(0, 23))->addMinutes(rand(0, 59));
        $note = $this->generateRandomNote();
        
        // Create follow-up (simulating QuickActions component logic)
        $followUp = FollowUp::create([
            'contact_id' => $contact->id,
            'user_id' => $user->id,
            'scheduled_date' => $scheduledDate,
            'note' => $note,
            'status' => 'pending',
        ]);
        
        // Query the follow-up fresh from database
        $queriedFollowUp = FollowUp::find($followUp->id);
        
        // PROPERTY: The follow-up must exist
        $this->assertNotNull(
            $queriedFollowUp,
            "Follow-up should exist after creation"
        );
        
        // PROPERTY: The scheduled date must match
        $this->assertEquals(
            $scheduledDate->format('Y-m-d H:i'),
            $queriedFollowUp->scheduled_date->format('Y-m-d H:i'),
            "Scheduled date should match. Expected: {$scheduledDate->format('Y-m-d H:i')}, " .
            "Got: {$queriedFollowUp->scheduled_date->format('Y-m-d H:i')}"
        );
        
        // PROPERTY: The note must match
        $this->assertEquals(
            $note,
            $queriedFollowUp->note,
            "Note should match. Expected: '{$note}', Got: '{$queriedFollowUp->note}'"
        );
        
        // PROPERTY: The contact_id must match
        $this->assertEquals(
            $contact->id,
            $queriedFollowUp->contact_id,
            "Contact ID should match"
        );
        
        // PROPERTY: The user_id must match
        $this->assertEquals(
            $user->id,
            $queriedFollowUp->user_id,
            "User ID should match"
        );
        
        // Clean up for next iteration
        FollowUp::query()->delete();
        Contact::query()->delete();
        Channel::query()->delete();
        User::query()->delete();
    }

    private function runMultipleFollowUpsIteration(): void
    {
        // Create user, channel and contact
        $user = User::factory()->create();
        $channel = Channel::factory()->create(['is_active' => true]);
        
        $contact = Contact::factory()->create([
            'channel_id' => $channel->id,
        ]);
        
        // Create random number of follow-ups (2-5)
        $followUpCount = rand(2, 5);
        $createdFollowUps = [];
        
        for ($f = 0; $f < $followUpCount; $f++) {
            $scheduledDate = now()->addDays(rand(1, 30))->addHours(rand(0, 23))->addMinutes(rand(0, 59));
            $note = $this->generateRandomNote();
            
            $followUp = FollowUp::create([
                'contact_id' => $contact->id,
                'user_id' => $user->id,
                'scheduled_date' => $scheduledDate,
                'note' => $note,
                'status' => 'pending',
            ]);
            
            $createdFollowUps[] = [
                'id' => $followUp->id,
                'scheduled_date' => $scheduledDate->format('Y-m-d H:i'),
                'note' => $note,
            ];
        }
        
        // Query all follow-ups for the contact
        $queriedFollowUps = $contact->followUps()->get();
        
        // PROPERTY: The count must match
        $this->assertEquals(
            $followUpCount,
            $queriedFollowUps->count(),
            "Follow-up count should match. Expected: {$followUpCount}, Got: {$queriedFollowUps->count()}"
        );
        
        // PROPERTY: Each created follow-up must be retrievable with correct data
        foreach ($createdFollowUps as $created) {
            $found = $queriedFollowUps->firstWhere('id', $created['id']);
            
            $this->assertNotNull(
                $found,
                "Follow-up with ID {$created['id']} should be found"
            );
            
            $this->assertEquals(
                $created['scheduled_date'],
                $found->scheduled_date->format('Y-m-d H:i'),
                "Scheduled date should match for follow-up {$created['id']}"
            );
            
            $this->assertEquals(
                $created['note'],
                $found->note,
                "Note should match for follow-up {$created['id']}"
            );
        }
        
        // Clean up for next iteration
        FollowUp::query()->delete();
        Contact::query()->delete();
        Channel::query()->delete();
        User::query()->delete();
    }

    private function runStatusPersistenceIteration(): void
    {
        // Create user, channel and contact
        $user = User::factory()->create();
        $channel = Channel::factory()->create(['is_active' => true]);
        
        $contact = Contact::factory()->create([
            'channel_id' => $channel->id,
        ]);
        
        // Generate random follow-up data
        $scheduledDate = now()->addDays(rand(1, 30));
        $note = $this->generateRandomNote();
        
        // Create follow-up with pending status
        $followUp = FollowUp::create([
            'contact_id' => $contact->id,
            'user_id' => $user->id,
            'scheduled_date' => $scheduledDate,
            'note' => $note,
            'status' => 'pending',
        ]);
        
        // Query the follow-up fresh from database
        $queriedFollowUp = FollowUp::find($followUp->id);
        
        // PROPERTY: The initial status must be 'pending'
        $this->assertEquals(
            'pending',
            $queriedFollowUp->status,
            "Initial status should be 'pending'. Got: '{$queriedFollowUp->status}'"
        );
        
        // PROPERTY: completed_at should be null for pending follow-ups
        $this->assertNull(
            $queriedFollowUp->completed_at,
            "completed_at should be null for pending follow-ups"
        );
        
        // Clean up for next iteration
        FollowUp::query()->delete();
        Contact::query()->delete();
        Channel::query()->delete();
        User::query()->delete();
    }

    /**
     * Generate a random note string.
     */
    private function generateRandomNote(): ?string
    {
        // 30% chance of null note
        if (rand(1, 10) <= 3) {
            return null;
        }
        
        $notes = [
            'Llamar para confirmar pago',
            'Verificar transferencia',
            'Enviar recordatorio de vencimiento',
            'Consultar sobre plan de pagos',
            'Seguimiento de promesa anterior',
            'Revisar estado de cuenta',
            'Contactar por WhatsApp',
            'Verificar datos de contacto',
            'Confirmar recepción de factura',
            'Negociar nuevo plazo',
        ];
        
        // Sometimes combine notes
        if (rand(1, 10) <= 2) {
            return $notes[array_rand($notes)] . '. ' . $notes[array_rand($notes)];
        }
        
        return $notes[array_rand($notes)];
    }
}
