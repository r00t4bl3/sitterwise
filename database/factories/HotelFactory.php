<?php

namespace Database\Factories;

use App\Models\Hotel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Hotel>
 */
class HotelFactory extends Factory
{
    protected $model = Hotel::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Hotel',
            'line1' => fake()->streetAddress(),
            'line2' => null,
            'city' => 'San Diego',
            'state' => 'CA',
            'zip' => fake()->postcode(),
            'parking_instructions' => fake()->optional()->sentence(),
            'hourly_rate' => fake()->randomFloat(2, 18, 30),
            'resort_fee' => fake()->optional()->randomFloat(2, 10, 25),
            'contact_name' => fake()->optional()->name(),
            'contact_phone' => fake()->optional()->phoneNumber(),
            'admin_notes' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the hotel is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
