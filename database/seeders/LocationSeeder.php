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
            [
                'name' => 'South County',
                'cities' => 'Coronado, Downtown, La Jolla, Mission Valley, Chula Vista, El Cajon, La Mesa',
                'svg_icon' => '<svg viewBox="0 0 24 24"><path d="M12 2L8 6.5V10H4v12h16V10h-4V6.5L12 2zm0 2.5L14.5 8V10h-5V8L12 4.5zM6 20v-8h3v3h6v-3h3v8H6z"/></svg>',
            ],
            [
                'name' => 'North County',
                'cities' => 'Rancho Santa Fe, Del Mar, Carlsbad, Encinitas, Escondido, San Marcos, Vista',
                'svg_icon' => '<svg viewBox="0 0 24 24"><path d="M12 2C8.7 2 6 5 6 8.5c0 3.2 3 6.8 5 9l1 1.2 1-1.2c2-2.2 5-5.8 5-9C18 5 15.3 2 12 2zm0 1.5c2.5 0 4.5 2.2 4.5 5 0 2.5-2.3 5.5-4.5 8-2.2-2.5-4.5-5.5-4.5-8 0-2.8 2-5 4.5-5zM10.5 19.5h3V22h-3v-2.5z"/></svg>',
            ],
        ];

        foreach ($locations as $location) {
            Location::create($location);
        }
    }
}
