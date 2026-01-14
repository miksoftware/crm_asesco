<?php

namespace Tests\Properties\Chat;

use App\Models\Channel;
use App\Models\Contact;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Property-Based Test: Search Filter Correctness
 * 
 * Feature: chat-module, Property 5: Search Filter Correctness
 * Validates: Requirements 2.4
 * 
 * For any search query, all returned conversations SHALL have a contact 
 * whose name OR phone number contains the search term (case-insensitive).
 */
class SearchFilterPropertyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Property 5: Search Filter Correctness
     * 
     * For any search query, all returned conversations SHALL have a contact 
     * whose name OR phone number contains the search term (case-insensitive).
     * 
     * @test
     */
    public function search_filter_correctness_property(): void
    {
        // Run 100 iterations with random data
        for ($i = 0; $i < 100; $i++) {
            $this->runSearchFilterIteration();
        }
    }

    private function runSearchFilterIteration(): void
    {
        // Create channel
        $channel = Channel::factory()->create(['is_active' => true]);
        
        // Create random number of contacts (5-15) with messages
        $contactCount = rand(5, 15);
        $contacts = [];
        
        for ($j = 0; $j < $contactCount; $j++) {
            $contact = Contact::factory()->create([
                'channel_id' => $channel->id,
                'name' => $this->generateRandomName(),
                'push_name' => $this->generateRandomName(),
                'phone_number' => $this->generateRandomPhoneNumber(),
            ]);
            
            // Create at least one message for each contact
            Message::factory()->create([
                'contact_id' => $contact->id,
                'channel_id' => $channel->id,
                'sent_at' => now()->subMinutes(rand(1, 1000)),
            ]);
            
            $contacts[] = $contact;
        }
        
        // Pick a random contact and extract a search term from their data
        $targetContact = $contacts[array_rand($contacts)];
        $searchTerm = $this->extractSearchTerm($targetContact);
        
        // Apply search filter (simulating the component logic)
        $searchTermLower = '%' . strtolower($searchTerm) . '%';
        
        $filteredConversations = Contact::where('channel_id', $channel->id)
            ->whereHas('messages')
            ->where(function ($q) use ($searchTermLower) {
                $q->whereRaw('LOWER(name) LIKE ?', [$searchTermLower])
                    ->orWhereRaw('LOWER(push_name) LIKE ?', [$searchTermLower])
                    ->orWhereRaw('LOWER(phone_number) LIKE ?', [$searchTermLower]);
            })
            ->get();
        
        // PROPERTY: All returned conversations must have name, push_name, or phone_number 
        // containing the search term (case-insensitive)
        foreach ($filteredConversations as $contact) {
            $nameContains = $contact->name && stripos($contact->name, $searchTerm) !== false;
            $pushNameContains = $contact->push_name && stripos($contact->push_name, $searchTerm) !== false;
            $phoneContains = stripos($contact->phone_number, $searchTerm) !== false;
            
            $this->assertTrue(
                $nameContains || $pushNameContains || $phoneContains,
                "Contact {$contact->id} does not contain search term '{$searchTerm}' in name, push_name, or phone_number. " .
                "Name: {$contact->name}, Push Name: {$contact->push_name}, Phone: {$contact->phone_number}"
            );
        }
        
        // Also verify that the target contact is in the results
        $this->assertTrue(
            $filteredConversations->contains('id', $targetContact->id),
            "Target contact should be in search results for term '{$searchTerm}'"
        );
        
        // Clean up for next iteration
        Message::query()->delete();
        Contact::query()->delete();
        Channel::query()->delete();
    }

    private function generateRandomName(): string
    {
        $firstNames = ['Juan', 'María', 'Carlos', 'Ana', 'Pedro', 'Laura', 'Diego', 'Sofia', 'Miguel', 'Carmen'];
        $lastNames = ['García', 'Rodríguez', 'Martínez', 'López', 'González', 'Hernández', 'Pérez', 'Sánchez'];
        
        return $firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)];
    }

    private function generateRandomPhoneNumber(): string
    {
        return '57' . rand(300, 350) . rand(1000000, 9999999);
    }

    private function extractSearchTerm(Contact $contact): string
    {
        // Randomly choose to search by name, push_name, or phone
        $choice = rand(1, 3);
        
        switch ($choice) {
            case 1:
                // Extract part of name
                if ($contact->name) {
                    $parts = explode(' ', $contact->name);
                    return $parts[array_rand($parts)];
                }
                // Fall through to phone if no name
            case 2:
                // Extract part of push_name
                if ($contact->push_name) {
                    $parts = explode(' ', $contact->push_name);
                    return $parts[array_rand($parts)];
                }
                // Fall through to phone if no push_name
            case 3:
            default:
                // Extract part of phone number (last 4-6 digits)
                $length = rand(4, 6);
                return substr($contact->phone_number, -$length);
        }
    }
}
