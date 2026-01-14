<?php

namespace Database\Factories;

use App\Models\Channel;
use App\Models\ChatNotification;
use App\Models\Contact;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ChatNotification>
 */
class ChatNotificationFactory extends Factory
{
    protected $model = ChatNotification::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'contact_id' => Contact::factory(),
            'channel_id' => Channel::factory(),
            'message_id' => Message::factory(),
            'type' => 'new_message',
            'title' => fake()->name(),
            'body' => fake()->sentence(),
            'is_read' => false,
            'read_at' => null,
        ];
    }

    /**
     * Indicate that the notification is read.
     */
    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    /**
     * Indicate that the notification is unread.
     */
    public function unread(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_read' => false,
            'read_at' => null,
        ]);
    }
}
