<?php

namespace Tests\Properties\Chat;

use App\Models\Channel;
use App\Models\Contact;
use App\Models\Message;
use App\Services\MessageService;
use App\Services\EvolutionApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Mockery;

/**
 * Property-Based Test: Pagination Ordering
 * 
 * Feature: chat-module, Property 8: Pagination Ordering
 * Validates: Requirements 3.4
 * 
 * For any paginated message load with a beforeId parameter, all returned 
 * messages SHALL have an ID less than beforeId and be sorted by sent_at 
 * in ascending order.
 */
class PaginationOrderingPropertyTest extends TestCase
{
    use RefreshDatabase;

    private MessageService $messageService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock EvolutionApiService since we don't need it for pagination tests
        $mockEvolutionApi = Mockery::mock(EvolutionApiService::class);
        $this->messageService = new MessageService($mockEvolutionApi);
    }

    /**
     * Property 8: Pagination Ordering
     * 
     * For any paginated message load with a beforeId parameter, all returned 
     * messages SHALL have an ID less than beforeId and be sorted by sent_at 
     * in ascending order.
     * 
     * @test
     */
    public function pagination_ordering_property(): void
    {
        // Run 100 iterations with random data
        for ($i = 0; $i < 100; $i++) {
            $this->runPaginationOrderingIteration();
        }
    }

    private function runPaginationOrderingIteration(): void
    {
        // Create channel and contact
        $channel = Channel::factory()->create();
        $contact = Contact::factory()->create(['channel_id' => $channel->id]);
        
        // Create random number of messages (10-30) with varying timestamps
        $messageCount = rand(10, 30);
        $messages = [];
        
        for ($j = 0; $j < $messageCount; $j++) {
            $messages[] = Message::factory()->create([
                'contact_id' => $contact->id,
                'channel_id' => $channel->id,
                'sent_at' => now()->subMinutes(rand(1, 1000)),
            ]);
        }
        
        // Get all message IDs sorted by ID
        $allMessageIds = Message::where('contact_id', $contact->id)
            ->orderBy('id')
            ->pluck('id')
            ->toArray();
        
        // Pick a random beforeId (not the first one)
        if (count($allMessageIds) > 1) {
            $beforeIdIndex = rand(1, count($allMessageIds) - 1);
            $beforeId = $allMessageIds[$beforeIdIndex];
            
            // Get paginated messages
            $paginatedMessages = $this->messageService->getConversationMessages(
                $contact->id,
                $channel->id,
                50,
                $beforeId
            );
            
            // PROPERTY 1: All returned messages must have ID < beforeId
            foreach ($paginatedMessages as $message) {
                $this->assertLessThan(
                    $beforeId,
                    $message->id,
                    "Message ID {$message->id} is not less than beforeId {$beforeId}"
                );
            }
            
            // PROPERTY 2: Messages must be sorted by sent_at in ascending order
            $previousSentAt = null;
            $previousId = null;
            
            foreach ($paginatedMessages as $message) {
                if ($previousSentAt !== null) {
                    // sent_at should be >= previous (ascending order)
                    $this->assertTrue(
                        $message->sent_at >= $previousSentAt || 
                        ($message->sent_at == $previousSentAt && $message->id >= $previousId),
                        "Messages are not sorted by sent_at in ascending order"
                    );
                }
                $previousSentAt = $message->sent_at;
                $previousId = $message->id;
            }
        }
        
        // Clean up for next iteration
        Message::query()->delete();
        Contact::query()->delete();
        Channel::query()->delete();
    }
}
