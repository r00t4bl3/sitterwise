<?php

namespace Database\Factories;

use App\Enums\SubmissionType;
use App\Models\BookingGroup;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookingGroupFactory extends Factory
{
    protected $model = BookingGroup::class;

    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'submitted_at' => now(),
            'submission_type' => SubmissionType::LoggedIn->value,
            'is_split' => false,
        ];
    }

    public function guest(): static
    {
        return $this->state(fn (array $attributes) => [
            'submission_type' => SubmissionType::Guest->value,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'submission_type' => SubmissionType::Admin->value,
        ]);
    }
}
