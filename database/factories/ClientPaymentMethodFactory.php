<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\ClientPaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientPaymentMethod>
 */
class ClientPaymentMethodFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'provider' => 'stripe',
            'provider_method_id' => 'pm_'.fake()->unique()->uuid(),
            'brand' => fake()->randomElement(['visa', 'mastercard', 'amex']),
            'last4' => fake()->numerify('####'),
            'exp_month' => fake()->numberBetween(1, 12),
            'exp_year' => fake()->numberBetween(2025, 2030),
            'status' => 'active',
            'metadata' => null,
            'is_default' => false,
        ];
    }
}
