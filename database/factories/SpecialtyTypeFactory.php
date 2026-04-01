<?php
namespace Database\Factories;

use App\Models\SpecialtyType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpecialtyType>
 */
class SpecialtyTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'        => fake()->unique()->word() . ' Specialty',
            'description' => fake()->sentence(),
            'is_active'   => true,
            'sort_order'  => fake()->numberBetween(1, 100),
        ];
    }
}
