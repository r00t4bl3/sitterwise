<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\ClientPet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientPet>
 */
class ClientPetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'name' => fake()->firstName(),
            'type' => fake()->randomElement(['dog', 'cat', 'other']),
            'breed' => fake()->word(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
