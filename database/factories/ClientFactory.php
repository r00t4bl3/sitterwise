<?php

namespace Database\Factories;

use App\Enums\ClientType;
use App\Enums\DiscoverySource;
use App\Enums\SitterPreference;
use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'biography' => $this->faker->optional()->paragraph(),
            'phone' => fake()->phoneNumber(),
            'client_type' => fake()->randomElement(ClientType::cases())->value,
            'corporate_id' => null,
            'stripe_customer_id' => null,
            'how_did_you_hear' => fake()->randomElement(DiscoverySource::cases())->value,
            'sitter_preferences' => fake()->randomElements(array_column(SitterPreference::cases(), 'value'), fake()->numberBetween(1, 4)),
            'other_adults_present' => fake()->numberBetween(0, 5),
            'emergency_instructions' => fake()->optional()->paragraph(),
            'special_needs_notes' => fake()->optional()->paragraph(),
        ];
    }
}
