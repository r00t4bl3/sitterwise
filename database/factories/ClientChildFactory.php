<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\ClientChild;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientChild>
 */
class ClientChildFactory extends Factory
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
            'gender' => fake()->randomElement(['male', 'female']),
            'birth_date' => fake()->dateTimeBetween('-15 years', '-1 year')->format('Y-m-d'),
        ];
    }
}
