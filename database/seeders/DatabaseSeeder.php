<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CaregiverStatusSeeder::class,
            CertificationTypeSeeder::class,
            SpecialtyTypeSeeder::class,
            LocationSeeder::class,
            AttributeDefinitionSeeder::class,
            CaregiverSeeder::class,
            AvailabilitySeeder::class,
        ]);

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'admin@example.test',
            'role' => 'admin',
        ]);
    }
}
