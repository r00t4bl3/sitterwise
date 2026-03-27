<?php

namespace Database\Seeders;

use App\Models\Availability;
use App\Models\Caregiver;
use Illuminate\Database\Seeder;

class AvailabilitySeeder extends Seeder
{
    public function run(): void
    {
        $caregivers = Caregiver::limit(41)->get();
        $startDate = now()->addDay()->startOfDay();

        $timeSlotOptions = [
            ['morning'],
            ['morning', 'afternoon'],
            ['afternoon', 'evening'],
            ['morning', 'afternoon', 'evening'],
            ['afternoon'],
            ['evening'],
            ['morning', 'evening'],
        ];

        $specificTimes = [
            null,
            'After 9am',
            'Before 5pm',
            'Available all day',
            'After 2pm',
            null,
            'Before 6pm',
        ];

        foreach ($caregivers as $index => $caregiver) {
            for ($i = 0; $i < 7; $i++) {
                $date = $startDate->copy()->addDays($i);
                $timeSlots = $timeSlotOptions[array_rand($timeSlotOptions)];
                $specificTime = $specificTimes[array_rand($specificTimes)];

                Availability::create([
                    'caregiver_id' => $caregiver->id,
                    'date' => $date->toDateString(),
                    'time_slots' => $timeSlots,
                    'specific_time' => $specificTime,
                ]);
            }
        }
    }
}
