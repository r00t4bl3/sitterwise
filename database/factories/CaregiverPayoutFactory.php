<?php

namespace Database\Factories;

use App\Models\Caregiver;
use App\Models\CaregiverPayout;
use App\Models\CaregiverPayoutMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CaregiverPayout>
 */
class CaregiverPayoutFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'caregiver_id' => Caregiver::factory(),
            'caregiver_payout_method_id' => CaregiverPayoutMethod::factory(),
            'amount' => fake()->numberBetween(10000, 100000), // Amount in cents
            'currency' => 'USD',
            'status' => fake()->randomElement(['pending', 'completed', 'failed']),
            'provider_transfer_id' => 'tr_'.fake()->unique()->uuid(),
            'payout_date' => fake()->dateTimeBetween('-30 days', '+30 days'),
        ];
    }
}
