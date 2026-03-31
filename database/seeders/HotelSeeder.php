<?php

namespace Database\Seeders;

use App\Models\Hotel;
use Illuminate\Database\Seeder;

class HotelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $hotels = [
            [
                'name' => 'Marriott Gaslamp',
                'line1' => '660 K St',
                'line2' => null,
                'city' => 'San Diego',
                'state' => 'CA',
                'zip' => '92101',
                'parking_instructions' => 'Self-park in attached garage, Level 2. Validate at front desk.',
                'hourly_rate' => 22.00,
                'resort_fee' => 12.00,
                'contact_name' => 'Maria Santos',
                'contact_phone' => '+1 619-446-6000',
                'admin_notes' => 'Primary hotel partner for downtown events.',
                'is_active' => true,
            ],
            [
                'name' => 'Hilton Bayfront',
                'line1' => '401 Park Blvd',
                'line2' => null,
                'city' => 'San Diego',
                'state' => 'CA',
                'zip' => '92101',
                'parking_instructions' => 'Valet parking available. Daily rate $45, self-park in adjacent lot.',
                'hourly_rate' => 22.00,
                'resort_fee' => 15.00,
                'contact_name' => 'Front Desk',
                'contact_phone' => '+1 619-232-3861',
                'admin_notes' => null,
                'is_active' => true,
            ],
            [
                'name' => 'Hotel del Coronado',
                'line1' => '1500 Orange Ave',
                'line2' => null,
                'city' => 'Coronado',
                'state' => 'CA',
                'zip' => '92118',
                'parking_instructions' => 'Valet only. Self-park at nearby lot on Orange Ave.',
                'hourly_rate' => 25.00,
                'resort_fee' => 30.00,
                'contact_name' => 'Concierge',
                'contact_phone' => '+1 619-435-6611',
                'admin_notes' => 'Premium property - higher rates apply.',
                'is_active' => true,
            ],
            [
                'name' => 'Manchester Grand Hyatt',
                'line1' => '1 Market Pl',
                'line2' => null,
                'city' => 'San Diego',
                'state' => 'CA',
                'zip' => '92101',
                'parking_instructions' => 'Underground parking available. Validate at front desk.',
                'hourly_rate' => 22.00,
                'resort_fee' => 10.00,
                'contact_name' => 'Events Desk',
                'contact_phone' => '+1 619-554-1500',
                'admin_notes' => null,
                'is_active' => true,
            ],
            [
                'name' => 'Pendry San Diego',
                'line1' => '550 J St',
                'line2' => null,
                'city' => 'San Diego',
                'state' => 'CA',
                'zip' => '92101',
                'parking_instructions' => 'Valet only. $50 per night.',
                'hourly_rate' => 28.00,
                'resort_fee' => 25.00,
                'contact_name' => 'Guest Services',
                'contact_phone' => '+1 619-756-7000',
                'admin_notes' => 'Boutique hotel - limited availability during peak seasons.',
                'is_active' => true,
            ],
        ];

        foreach ($hotels as $hotel) {
            Hotel::create($hotel);
        }
    }
}
