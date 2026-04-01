<?php

namespace Database\Factories;

use App\Models\CertificationType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CertificationType>
 */
class CertificationTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word().' Certification',
            'description' => fake()->sentence(),
            'expires_required' => fake()->boolean(),
            'is_active' => true,
        ];
    }
}
