<?php

namespace Tests\Properties\Chat;

use App\Models\Channel;
use App\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Property-Based Test: Contact Edit Persistence
 * 
 * Feature: chat-module, Property 20: Contact Edit Persistence
 * Validates: Requirements 8.3
 * 
 * For any contact name or notes update, querying the contact after the update 
 * SHALL return the new values.
 */
class ContactEditPersistencePropertyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Property 20: Contact Edit Persistence - Name Update
     * 
     * For any contact and any valid name string, updating the name then 
     * querying the contact SHALL return the contact with the new name.
     * 
     * @test
     */
    public function contact_name_edit_persistence_property(): void
    {
        // Run 100 iterations with random data
        for ($i = 0; $i < 100; $i++) {
            $this->runNameEditIteration();
        }
    }

    /**
     * Property 20: Contact Edit Persistence - Notes Update
     * 
     * For any contact and any valid notes string, updating the notes then 
     * querying the contact SHALL return the contact with the new notes.
     * 
     * @test
     */
    public function contact_notes_edit_persistence_property(): void
    {
        // Run 100 iterations with random data
        for ($i = 0; $i < 100; $i++) {
            $this->runNotesEditIteration();
        }
    }

    /**
     * Property 20: Contact Edit Persistence - Combined Update
     * 
     * For any contact, updating both name and notes then querying the contact 
     * SHALL return the contact with both new values.
     * 
     * @test
     */
    public function contact_combined_edit_persistence_property(): void
    {
        // Run 100 iterations with random data
        for ($i = 0; $i < 100; $i++) {
            $this->runCombinedEditIteration();
        }
    }

    /**
     * Property 20: Contact Edit Persistence - Clear Values
     * 
     * For any contact with name and notes, clearing these values (setting to null)
     * then querying the contact SHALL return the contact with null values.
     * 
     * @test
     */
    public function contact_clear_values_persistence_property(): void
    {
        // Run 100 iterations with random data
        for ($i = 0; $i < 100; $i++) {
            $this->runClearValuesIteration();
        }
    }

    private function runNameEditIteration(): void
    {
        // Create channel and contact
        $channel = Channel::factory()->create(['is_active' => true]);
        
        // Generate random initial name (may be null)
        $initialName = rand(0, 1) ? $this->generateRandomName() : null;
        
        $contact = Contact::factory()->create([
            'channel_id' => $channel->id,
            'name' => $initialName,
        ]);
        
        // Generate new random name
        $newName = $this->generateRandomName();
        
        // Update the contact name (simulating the component logic)
        $trimmedName = trim($newName);
        $contact->update([
            'name' => $trimmedName !== '' ? $trimmedName : null,
        ]);
        
        // Query the contact fresh from database
        $queriedContact = Contact::find($contact->id);
        
        // PROPERTY: The updated name must match the queried contact's name
        $expectedName = $trimmedName !== '' ? $trimmedName : null;
        $this->assertEquals(
            $expectedName,
            $queriedContact->name,
            "After updating name to '{$newName}', queried contact should have name '{$expectedName}'. " .
            "Initial name: " . ($initialName ?? 'null') . ", " .
            "Queried name: " . ($queriedContact->name ?? 'null')
        );
        
        // Clean up for next iteration
        Contact::query()->delete();
        Channel::query()->delete();
    }

    private function runNotesEditIteration(): void
    {
        // Create channel and contact
        $channel = Channel::factory()->create(['is_active' => true]);
        
        // Generate random initial notes (may be null)
        $initialNotes = rand(0, 1) ? $this->generateRandomNotes() : null;
        
        $contact = Contact::factory()->create([
            'channel_id' => $channel->id,
            'notes' => $initialNotes,
        ]);
        
        // Generate new random notes
        $newNotes = $this->generateRandomNotes();
        
        // Update the contact notes (simulating the component logic)
        $trimmedNotes = trim($newNotes);
        $contact->update([
            'notes' => $trimmedNotes !== '' ? $trimmedNotes : null,
        ]);
        
        // Query the contact fresh from database
        $queriedContact = Contact::find($contact->id);
        
        // PROPERTY: The updated notes must match the queried contact's notes
        $expectedNotes = $trimmedNotes !== '' ? $trimmedNotes : null;
        $this->assertEquals(
            $expectedNotes,
            $queriedContact->notes,
            "After updating notes, queried contact should have the new notes. " .
            "Initial notes: " . ($initialNotes ?? 'null') . ", " .
            "Queried notes: " . ($queriedContact->notes ?? 'null')
        );
        
        // Clean up for next iteration
        Contact::query()->delete();
        Channel::query()->delete();
    }

    private function runCombinedEditIteration(): void
    {
        // Create channel and contact
        $channel = Channel::factory()->create(['is_active' => true]);
        
        // Generate random initial values
        $initialName = rand(0, 1) ? $this->generateRandomName() : null;
        $initialNotes = rand(0, 1) ? $this->generateRandomNotes() : null;
        
        $contact = Contact::factory()->create([
            'channel_id' => $channel->id,
            'name' => $initialName,
            'notes' => $initialNotes,
        ]);
        
        // Generate new random values
        $newName = $this->generateRandomName();
        $newNotes = $this->generateRandomNotes();
        
        // Update both values (simulating the component logic)
        $trimmedName = trim($newName);
        $trimmedNotes = trim($newNotes);
        
        $contact->update([
            'name' => $trimmedName !== '' ? $trimmedName : null,
            'notes' => $trimmedNotes !== '' ? $trimmedNotes : null,
        ]);
        
        // Query the contact fresh from database
        $queriedContact = Contact::find($contact->id);
        
        // PROPERTY: Both updated values must match the queried contact
        $expectedName = $trimmedName !== '' ? $trimmedName : null;
        $expectedNotes = $trimmedNotes !== '' ? $trimmedNotes : null;
        
        $this->assertEquals(
            $expectedName,
            $queriedContact->name,
            "After combined update, name should match expected value"
        );
        
        $this->assertEquals(
            $expectedNotes,
            $queriedContact->notes,
            "After combined update, notes should match expected value"
        );
        
        // Clean up for next iteration
        Contact::query()->delete();
        Channel::query()->delete();
    }

    private function runClearValuesIteration(): void
    {
        // Create channel and contact with values
        $channel = Channel::factory()->create(['is_active' => true]);
        
        $contact = Contact::factory()->create([
            'channel_id' => $channel->id,
            'name' => $this->generateRandomName(),
            'notes' => $this->generateRandomNotes(),
        ]);
        
        // Verify initial values are set
        $this->assertNotNull($contact->name);
        $this->assertNotNull($contact->notes);
        
        // Clear values (simulating the component logic with empty strings)
        $contact->update([
            'name' => null,
            'notes' => null,
        ]);
        
        // Query the contact fresh from database
        $queriedContact = Contact::find($contact->id);
        
        // PROPERTY: Cleared values must be null in the queried contact
        $this->assertNull(
            $queriedContact->name,
            "After clearing name, queried contact should have null name"
        );
        
        $this->assertNull(
            $queriedContact->notes,
            "After clearing notes, queried contact should have null notes"
        );
        
        // Clean up for next iteration
        Contact::query()->delete();
        Channel::query()->delete();
    }

    /**
     * Generate a random name string.
     */
    private function generateRandomName(): string
    {
        $firstNames = ['Juan', 'María', 'Carlos', 'Ana', 'Pedro', 'Laura', 'Diego', 'Sofia', 'Miguel', 'Carmen'];
        $lastNames = ['García', 'Rodríguez', 'Martínez', 'López', 'González', 'Hernández', 'Pérez', 'Sánchez'];
        
        return $firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)];
    }

    /**
     * Generate random notes string.
     */
    private function generateRandomNotes(): string
    {
        $noteTemplates = [
            'Cliente desde hace %d años',
            'Prefiere contacto por %s',
            'Horario preferido: %s',
            'Deuda pendiente de %d meses',
            'Último pago: hace %d días',
            'Contactar antes de las %d:00',
            'Cliente VIP - prioridad alta',
            'Requiere seguimiento semanal',
            'Negociación en proceso',
            'Documentación pendiente',
        ];
        
        $template = $noteTemplates[array_rand($noteTemplates)];
        
        // Replace placeholders with random values
        $template = str_replace('%d', (string) rand(1, 30), $template);
        $template = str_replace('%s', ['WhatsApp', 'teléfono', 'email', 'mañana', 'tarde'][array_rand(['WhatsApp', 'teléfono', 'email', 'mañana', 'tarde'])], $template);
        
        return $template;
    }
}
