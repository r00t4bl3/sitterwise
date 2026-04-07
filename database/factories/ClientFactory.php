<?php

namespace Database\Factories;

use App\Enums\ClientType;
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
        $preferences = ['college_aged', 'seasoned', 'baby_specialist', 'special_needs_exp', 'willing_to_swim'];

        return [
            'user_id' => User::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => fake()->phoneNumber(),
            'client_type' => fake()->randomElement([ClientType::Resident->value, ClientType::Vacationer->value, ClientType::Invoiced->value]),
            'corporate_id' => null,
            'how_did_you_hear' => fake()->randomElement(['concierge', 'friend_family', 'google', 'returning_client', 'care_com', 'other']),
            'sitter_preferences' => fake()->randomElements($preferences, fake()->numberBetween(0, 3)),
            'other_adults_present' => fake()->numberBetween(0, 5),
            'emergency_instructions' => fake()->optional()->paragraph(),
            'special_needs_notes' => fake()->optional()->paragraph(),
        ];
    }
}
