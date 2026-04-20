<?php

namespace Database\Factories;

use App\Models\PricingRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PricingRule>
 */
class PricingRuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_type' => $this->faker->randomElement([
                'Babysitter',
                'Petsitter',
                'Companion Care',
                'Group Childcare (Invoiced)',
                'Corporate (Invoiced)',
                'Overnight Newborn Care',
                'Comped',
            ]),
            'number_of_children' => $this->faker->optional(0.7)->numberBetween(1, 5), // 70% chance of being a number
            'is_for_pets' => $this->faker->boolean(10), // 10% chance of being for pets
            'charge_to_client' => $this->faker->randomFloat(2, 20, 100),
            'charge_to_client_notes' => $this->faker->optional()->sentence(),
            'paid_to_caregiver' => $this->faker->randomFloat(2, 15, 70),
            'payment_form' => $this->faker->randomElement(['Stripe', 'OnPay (Payroll)']),
            'sitterwise_cut' => $this->faker->randomFloat(2, 5, 20),
        ];
    }
}
