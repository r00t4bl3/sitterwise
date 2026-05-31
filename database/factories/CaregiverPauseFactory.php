<?php

namespace Database\Factories;

use App\Models\Caregiver;
use App\Models\CaregiverPause;
use Illuminate\Database\Eloquent\Factories\Factory;

class CaregiverPauseFactory extends Factory
{
    protected $model = CaregiverPause::class;

    public function definition(): array
    {
        return [
            'caregiver_id' => Caregiver::factory(),
            'paused_at' => now(),
            'resume_by' => fake()->optional()->dateTimeBetween('+1 week', '+3 months'),
            'pause_reason' => fake()->optional()->sentence(),
            'resumed_at' => null,
        ];
    }

    public function resumed(): static
    {
        return $this->state(fn (array $attributes) => [
            'resumed_at' => now()->addDays(rand(7, 60)),
        ]);
    }
}
