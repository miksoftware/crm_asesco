<?php

namespace Tests\Properties\Chat;

use App\Models\Channel;
use App\Models\Contact;
use App\Models\Message;
use App\Models\User;
use App\Models\Role;
use App\Models\Module;
use App\Models\Permission;
use App\Livewire\Chat\Index;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Property-Based Test: Empty Message Validation
 * 
 * Feature: chat-module, Property 10: Empty Message Validation
 * Validates: Requirements 4.4
 * 
 * For any message text that is empty or contains only whitespace, 
 * the send operation SHALL be rejected and no message SHALL be created.
 */
class EmptyMessageValidationPropertyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Channel $channel;
    private Contact $contact;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create module and permissions for chat
        $module = Module::create([
            'name' => 'chats',
            'display_name' => 'Chats',
            'icon' => 'message-circle',
            'order' => 1,
        ]);
        
        Permission::create([
            'name' => 'chats.ver',
            'display_name' => 'Ver Chats',
            'module_id' => $module->id,
            'action' => 'ver',
        ]);
        
        $sendPermission = Permission::create([
            'name' => 'chats.enviar',
            'display_name' => 'Enviar Mensajes',
            'module_id' => $module->id,
            'action' => 'enviar',
        ]);
        
        // Create admin role with permissions
        $role = Role::create([
            'name' => 'admin',
            'display_name' => 'Administrador',
            'color' => '#000000',
        ]);
        $role->permissions()->attach($sendPermission);
        
        // Create user with admin role
        $this->user = User::factory()->create();
        $this->user->roles()->attach($role);
        
        // Create channel and assign to user
        $this->channel = Channel::factory()->create(['is_active' => true]);
        $this->user->channels()->attach($this->channel);
        
        // Create contact
        $this->contact = Contact::factory()->create([
            'channel_id' => $this->channel->id,
        ]);
    }

    /**
     * Property 10: Empty Message Validation
     * 
     * For any message text that is empty or contains only whitespace, 
     * the send operation SHALL be rejected and no message SHALL be created.
     * 
     * @test
     */
    public function empty_message_validation_property(): void
    {
        // Run 100 iterations with random whitespace-only strings
        for ($i = 0; $i < 100; $i++) {
            $this->runEmptyMessageValidationIteration();
        }
    }

    private function runEmptyMessageValidationIteration(): void
    {
        // Generate random whitespace-only string
        $whitespaceChars = [' ', "\t", "\n", "\r", '  ', "\t\t", "\n\n", '   ', "\r\n"];
        $emptyString = $this->generateRandomWhitespaceString($whitespaceChars);
        
        // Count messages before attempt
        $messageCountBefore = Message::where('contact_id', $this->contact->id)
            ->where('channel_id', $this->channel->id)
            ->count();
        
        // Attempt to send empty/whitespace message via Livewire component
        $component = Livewire::actingAs($this->user)
            ->test(Index::class)
            ->set('selectedChannelId', $this->channel->id)
            ->set('selectedContactId', $this->contact->id)
            ->set('messageText', $emptyString)
            ->call('sendMessage');
        
        // Count messages after attempt
        $messageCountAfter = Message::where('contact_id', $this->contact->id)
            ->where('channel_id', $this->channel->id)
            ->count();
        
        // PROPERTY: No message should be created for empty/whitespace input
        $this->assertEquals(
            $messageCountBefore,
            $messageCountAfter,
            "Message was created for empty/whitespace input: '{$emptyString}' (length: " . strlen($emptyString) . ")"
        );
    }

    /**
     * Generate a random string containing only whitespace characters.
     */
    private function generateRandomWhitespaceString(array $whitespaceChars): string
    {
        // Randomly decide: empty string or whitespace-only string
        $type = rand(0, 2);
        
        if ($type === 0) {
            // Empty string
            return '';
        }
        
        // Generate whitespace-only string of random length (1-20 chars)
        $length = rand(1, 20);
        $result = '';
        
        for ($i = 0; $i < $length; $i++) {
            $result .= $whitespaceChars[array_rand($whitespaceChars)];
        }
        
        return $result;
    }

    /**
     * Test specific edge cases for empty message validation.
     * 
     * @test
     */
    public function empty_message_edge_cases(): void
    {
        $edgeCases = [
            '',           // Empty string
            ' ',          // Single space
            '  ',         // Multiple spaces
            "\t",         // Tab
            "\n",         // Newline
            "\r",         // Carriage return
            "\r\n",       // Windows newline
            "   \t\n  ",  // Mixed whitespace
            "\t\t\t",     // Multiple tabs
            "\n\n\n",     // Multiple newlines
        ];
        
        foreach ($edgeCases as $emptyInput) {
            $messageCountBefore = Message::where('contact_id', $this->contact->id)
                ->where('channel_id', $this->channel->id)
                ->count();
            
            Livewire::actingAs($this->user)
                ->test(Index::class)
                ->set('selectedChannelId', $this->channel->id)
                ->set('selectedContactId', $this->contact->id)
                ->set('messageText', $emptyInput)
                ->call('sendMessage');
            
            $messageCountAfter = Message::where('contact_id', $this->contact->id)
                ->where('channel_id', $this->channel->id)
                ->count();
            
            $this->assertEquals(
                $messageCountBefore,
                $messageCountAfter,
                "Message was created for edge case input (escaped): " . json_encode($emptyInput)
            );
        }
    }
}
