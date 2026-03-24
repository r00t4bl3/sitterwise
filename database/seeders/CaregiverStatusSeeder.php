<?php

namespace Database\Seeders;

use App\Models\CaregiverStatus;
use Illuminate\Database\Seeder;

class CaregiverStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $statuses = [
            ['name' => 'Active', 'description' => 'Available for booking', 'color' => '#22C55E', 'sort_order' => 1],
            ['name' => 'Inactive', 'description' => 'Not currently available', 'color' => '#6B7280', 'sort_order' => 2],
            ['name' => 'In Process', 'description' => 'Onboarding in progress', 'color' => '#F59E0B', 'sort_order' => 3],
            ['name' => 'Non Starter', 'description' => 'Did not complete onboarding', 'color' => '#EF4444', 'sort_order' => 4],
            ['name' => 'Fired', 'description' => 'Terminated from service', 'color' => '#DC2626', 'sort_order' => 5],
            ['name' => 'Ineligible', 'description' => 'Does not meet requirements', 'color' => '#991B1B', 'sort_order' => 6],
        ];

        foreach ($statuses as $status) {
            CaregiverStatus::create($status);
        }
    }
}
