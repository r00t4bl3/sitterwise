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
            ['name' => 'Special Needs', 'slug' => 'special_needs', 'type' => 'boolean', 'entity_type' => 'caregiver', 'sort_order' => 6],
        ];

        // Client attributes
        $clientAttributes = [
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

        // Booking attributes
        $bookingAttributes = [
            ['name' => 'Vacation Rental Platform', 'slug' => 'vacation_rental_platform', 'type' => 'select', 'entity_type' => 'booking', 'sort_order' => 1, 'options' => ['airbnb', 'vrbo', 'booking.com', 'expedia', 'other']],
        ];

        foreach ($bookingAttributes as $attr) {
            AttributeDefinition::create($attr);
        }
    }
}
