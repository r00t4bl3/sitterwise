<?php

namespace Database\Factories;

use App\Models\Caregiver;
use App\Models\CaregiverPayoutMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CaregiverPayoutMethod>
 */
class CaregiverPayoutMethodFactory extends Factory
{
    public function definition(): array
    {
        return [
            'caregiver_id' => Caregiver::factory(),
            'provider' => 'stripe_connect',
            'provider_method_id' => 'ba_'.fake()->unique()->uuid(),
            'account_type' => fake()->randomElement(['checking', 'savings']),
            'bank_name' => fake()->randomElement(['Chase', 'Bank of America', 'Wells Fargo', 'Citibank']),
            'last4' => fake()->numerify('####'),
            'status' => 'active',
            'metadata' => null,
            'is_default' => false,
        ];
    }
}
