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
            ['name' => 'South County', 'description' => 'Southern area of the county'],
            ['name' => 'North County', 'description' => 'Northern area of the county'],
        ];

        foreach ($locations as $location) {
            Location::create($location);
        }
    }
}
