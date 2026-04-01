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
            ClientSeeder::class,
            AvailabilitySeeder::class,
            HotelSeeder::class,
            BookingGroupSeeder::class,
            BookingSeeder::class,
        ]);

        User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'superadmin@example.test',
            'password' => 'asdfasdf',
            'role' => 'super_admin',
        ]);

        User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => 'asdfasdf',
            'role' => 'admin',
        ]);

        User::factory()->create([
            'name' => 'Caregiver User',
            'email' => 'caregiver@example.test',
            'password' => 'asdfasdf',
            'role' => 'caregiver',
        ]);

        User::factory()->create([
            'name' => 'Client User',
            'email' => 'client@example.test',
            'password' => 'asdfasdf',
            'role' => 'client',
        ]);
    }
}
