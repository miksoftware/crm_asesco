<?php

namespace Database\Factories;

use App\Models\Channel;
use App\Models\Contact;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contact_id' => Contact::factory(),
            'channel_id' => Channel::factory(),
            'message_id' => fake()->uuid(),
            'direction' => fake()->randomElement(['incoming', 'outgoing']),
            'type' => 'text',
            'content' => fake()->sentence(),
            'media_url' => null,
            'media_mime_type' => null,
            'status' => fake()->randomElement(['pending', 'sent', 'delivered', 'read']),
            'is_read' => fake()->boolean(),
            'metadata' => [],
            'sent_at' => fake()->dateTimeBetween('-1 month', 'now'),
        ];
    }

    /**
     * Indicate that the message is incoming.
     */
    public function incoming(): static
    {
        return $this->state(fn (array $attributes) => [
            'direction' => 'incoming',
            'status' => 'delivered',
        ]);
    }

    /**
     * Indicate that the message is outgoing.
     */
    public function outgoing(): static
    {
        return $this->state(fn (array $attributes) => [
            'direction' => 'outgoing',
        ]);
    }

    /**
     * Indicate that the message is unread.
     */
    public function unread(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_read' => false,
        ]);
    }

    /**
     * Indicate that the message is read.
     */
    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_read' => true,
        ]);
    }
}
