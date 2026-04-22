<?php

namespace Database\Factories;

use App\Enums\LocationType;
use App\Models\Client;
use App\Models\ClientAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientAddress>
 */
class ClientAddressFactory extends Factory
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
            'label' => fake()->randomElement(['Home', 'Hotel', 'Airbnb', 'Vacation Rental']),
            'location_type' => fake()->randomElement(LocationType::cases())->value,
            'line1' => fake()->streetAddress(),
            'line2' => fake()->optional()->secondaryAddress(),
            'city' => fake()->city(),
            'state' => fake()->stateAbbr(),
            'zip' => fake()->numerify('#####'),
            'is_primary' => fake()->boolean(70),
        ];
    }
}
