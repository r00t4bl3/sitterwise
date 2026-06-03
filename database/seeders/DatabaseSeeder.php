<?php

namespace Database\Seeders;

use App\Models\Caregiver;
use App\Models\Client;
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
            QuickLinkSeeder::class,
            CertificationTypeSeeder::class,
            SpecialtyTypeSeeder::class,
            LocationSeeder::class,
            AttributeDefinitionSeeder::class,
            // CaregiverSeeder::class,
            // ClientSeeder::class,
            // AvailabilitySeeder::class,
            HotelSeeder::class,
            // BookingGroupSeeder::class,
            // BookingSeeder::class,
            PricingRulesTableSeeder::class,
            // ClientCaregiverRelationshipsSeeder::class,
        ]);

        User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'superadmin@example.test',
            'role' => 'super_admin',
        ]);

        User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'role' => 'admin',
        ]);

        $caregiver = User::factory()->create([
            'name' => 'Caregiver User',
            'email' => 'caregiver@example.test',
            'role' => 'caregiver',
        ]);

        Caregiver::factory()->create([
            'user_id' => $caregiver->id,
        ]);

        $client = User::factory()->create([
            'name' => 'Client User',
            'email' => 'client@example.test',
            'role' => 'client',
        ]);

        Client::factory()->create([
            'user_id' => $client->id,
        ]);

        // Seed a test applicant for development/staging
        $this->call([
            ApplicantSeeder::class,
        ]);

        // Run caregiver booking seeder after users are created
        $this->call([
            // CaregiverBookingSeeder::class,
        ]);
    }
}
