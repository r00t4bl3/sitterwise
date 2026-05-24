<?php

namespace Database\Seeders;

use App\Models\Location;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $locations = [
            ['name' => 'South County', 'description' => 'Coronado, Downtown, La Jolla, Mission Valley, Chula Vista, El Cajon, La Mesa'],
            ['name' => 'North County', 'description' => 'Rancho Santa Fe, Del Mar, Carlsbad, Encinitas, Escondido, San Marcos, Vista'],
        ];

        foreach ($locations as $location) {
            Location::create($location);
        }
    }
}
