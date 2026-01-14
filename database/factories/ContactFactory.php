<?php

namespace Database\Factories;

use App\Models\Channel;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Contact>
 */
class ContactFactory extends Factory
{
    protected $model = Contact::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'channel_id' => Channel::factory(),
            'phone_number' => fake()->unique()->numerify('57##########'),
            'name' => fake()->name(),
            'push_name' => fake()->firstName(),
            'profile_picture' => null,
            'notes' => fake()->optional()->sentence(),
            'labels' => [],
            'metadata' => [],
        ];
    }

    /**
     * Indicate that the contact has specific labels.
     */
    public function withLabels(array $labels): static
    {
        return $this->state(fn (array $attributes) => [
            'labels' => $labels,
        ]);
    }
}
