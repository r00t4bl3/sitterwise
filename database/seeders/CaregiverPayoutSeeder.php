<?php

namespace Database\Seeders;

use App\Models\CaregiverPayout;
use Illuminate\Database\Seeder;

class CaregiverPayoutSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        CaregiverPayout::factory()->count(10)->create();
    }
}
