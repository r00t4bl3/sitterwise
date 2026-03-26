<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CaregiverStatusFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->randomElement(['Active', 'Inactive', 'On Hold', 'Pending Review']),
            'color' => $this->faker->randomElement(['#10B981', '#EF4444', '#F59E0B', '#6366F1']),
            'is_active' => true,
            'sort_order' => $this->faker->numberBetween(1, 10),
        ];
    }
}
