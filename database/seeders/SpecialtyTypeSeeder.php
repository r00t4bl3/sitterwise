<?php

namespace Database\Seeders;

use App\Models\SpecialtyType;
use Illuminate\Database\Seeder;

class SpecialtyTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $specialties = [
            ['name' => 'Babies', 'description' => 'Infant care (0-12 months)', 'sort_order' => 1],
            ['name' => 'Toddlers', 'description' => 'Toddler care (1-3 years)', 'sort_order' => 2],
            ['name' => 'Preschool', 'description' => 'Preschool age (3-5 years)', 'sort_order' => 3],
            ['name' => 'School Age', 'description' => 'School age children (5+ years)', 'sort_order' => 4],
            ['name' => 'Special Needs', 'description' => 'Children with special requirements', 'sort_order' => 5],
        ];

        foreach ($specialties as $specialty) {
            SpecialtyType::create($specialty);
        }
    }
}
