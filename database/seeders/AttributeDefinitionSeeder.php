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
        // Caregiver attributes
        $caregiverAttributes = [
            ['name' => 'Pet Sitting', 'slug' => 'pet_sitting', 'type' => 'boolean', 'entity_type' => 'caregiver', 'sort_order' => 1],
            ['name' => 'Spanish', 'slug' => 'spanish', 'type' => 'boolean', 'entity_type' => 'caregiver', 'sort_order' => 2],
            ['name' => 'Has Vehicle', 'slug' => 'has_vehicle', 'type' => 'boolean', 'entity_type' => 'caregiver', 'sort_order' => 3],
            ['name' => 'Non-Smoker', 'slug' => 'non_smoker', 'type' => 'boolean', 'entity_type' => 'caregiver', 'sort_order' => 4],
            ['name' => 'COVID-19 Vaccinated', 'slug' => 'covid19_vaccinated', 'type' => 'boolean', 'entity_type' => 'caregiver', 'sort_order' => 5],
        ];

        // Client attributes
        $clientAttributes = [
            ['name' => 'Has Pets', 'slug' => 'has_pets', 'type' => 'boolean', 'entity_type' => 'client', 'sort_order' => 1],
            ['name' => 'Has Pool', 'slug' => 'has_pool', 'type' => 'boolean', 'entity_type' => 'client', 'sort_order' => 2],
            ['name' => 'Needs Night Care', 'slug' => 'needs_night_care', 'type' => 'boolean', 'entity_type' => 'client', 'sort_order' => 3],
            ['name' => 'Needs Weekend Care', 'slug' => 'needs_weekend_care', 'type' => 'boolean', 'entity_type' => 'client', 'sort_order' => 4],
            ['name' => 'Smoke Detectors', 'slug' => 'smoke_detectors', 'type' => 'boolean', 'entity_type' => 'client', 'sort_order' => 5],
        ];

        foreach ($caregiverAttributes as $attr) {
            AttributeDefinition::create($attr);
        }

        foreach ($clientAttributes as $attr) {
            AttributeDefinition::create($attr);
        }
    }
}
