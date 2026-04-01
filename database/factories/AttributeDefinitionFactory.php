<?php

namespace Database\Factories;

use App\Models\AttributeDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttributeDefinition>
 */
class AttributeDefinitionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word().' Attribute',
            'slug' => fake()->unique()->slug(),
            'type' => 'text',
            'entity_type' => 'client',
            'is_active' => true,
            'sort_order' => fake()->numberBetween(1, 100),
        ];
    }
}
