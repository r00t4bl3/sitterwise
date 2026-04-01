<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Database\Seeder;

class BookingSeeder extends Seeder
{
    public function run(): void
    {
        foreach (BookingStatus::cases() as $status) {
            Booking::factory()
                ->count(3)
                ->state(['status' => $status->value])
                ->create();
        }

        Booking::factory()
            ->count(5)
            ->confirmed()
            ->create();

        Booking::factory()
            ->count(3)
            ->completed()
            ->create();
    }
}
