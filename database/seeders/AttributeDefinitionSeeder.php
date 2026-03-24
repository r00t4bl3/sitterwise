<?php

namespace Database\Seeders;

use App\Models\AttributeDefinition;
use Illuminate\Database\Seeder;

class AttributeDefinitionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $attributes = [
            ['name' => 'Pet Sitting', 'slug' => 'pet_sitting', 'type' => 'boolean', 'sort_order' => 1],
            ['name' => 'Spanish', 'slug' => 'spanish', 'type' => 'boolean', 'sort_order' => 2],
            ['name' => 'Has Vehicle', 'slug' => 'has_vehicle', 'type' => 'boolean', 'sort_order' => 3],
            ['name' => 'Non-Smoker', 'slug' => 'non_smoker', 'type' => 'boolean', 'sort_order' => 4],
            ['name' => 'COVID-19 Vaccinated', 'slug' => 'covid19_vaccinated', 'type' => 'boolean', 'sort_order' => 5],
        ];

        foreach ($attributes as $attr) {
            AttributeDefinition::create($attr);
        }
    }
}
