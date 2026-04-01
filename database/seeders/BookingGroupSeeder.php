<?php

namespace Database\Seeders;

use App\Models\BookingGroup;
use Illuminate\Database\Seeder;

class BookingGroupSeeder extends Seeder
{
    public function run(): void
    {
        BookingGroup::factory()
            ->count(10)
            ->create();
    }
}
