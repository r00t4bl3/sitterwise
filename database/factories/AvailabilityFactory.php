<?php

namespace Database\Factories;

use App\Enums\TimeSlot;
use App\Models\Availability;
use App\Models\Caregiver;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Availability>
 */
class AvailabilityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'caregiver_id' => Caregiver::factory(),
            'date' => now()->addDays(rand(1, 30))->toDateString(),
            'time_slots' => $this->faker->randomElements(
                array_map(fn ($case) => $case->value, TimeSlot::cases()),
                $this->faker->numberBetween(1, 3),
            ),
            'specific_time' => 'Available all day',
        ];
    }
}
