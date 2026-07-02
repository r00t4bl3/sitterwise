<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClientPaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'client_id' => Client::factory(),
            'payment_method_id' => null,
            'amount' => fake()->randomFloat(2, 50, 500),
            'currency' => 'usd',
            'status' => fake()->randomElement(['pending', 'succeeded', 'failed', 'refunded']),
            'provider' => 'stripe',
            'provider_payment_id' => 'pi_'.fake()->unique()->uuid(),
            'provider_charge_id' => 'ch_'.fake()->unique()->uuid(),
            'paid_at' => fake()->dateTimeBetween('-1 year', 'now'),
        ];
    }

    public function succeeded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'succeeded',
            'paid_at' => now(),
        ]);
    }

    public function captured(): static
    {
        return $this->succeeded();
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'paid_at' => null,
        ]);
    }

    public function refunded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'refunded',
        ]);
    }
}
