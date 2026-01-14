<?php

namespace Database\Factories;

use App\Models\Channel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Channel>
 */
class ChannelFactory extends Factory
{
    protected $model = Channel::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company() . ' WhatsApp',
            'instance_name' => fake()->unique()->slug(2),
            'phone_number' => fake()->numerify('57##########'),
            'token' => fake()->uuid(),
            'status' => fake()->randomElement(['connected', 'disconnected', 'connecting']),
            'integration' => 'WHATSAPP-BAILEYS',
            'qr_code' => null,
            'settings' => [],
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the channel is connected.
     */
    public function connected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'connected',
        ]);
    }

    /**
     * Indicate that the channel is disconnected.
     */
    public function disconnected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'disconnected',
        ]);
    }
}
