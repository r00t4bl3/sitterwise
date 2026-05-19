<?php

namespace Database\Factories;

use App\Models\SmsBroadcast;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SmsBroadcastFactory extends Factory
{
    protected $model = SmsBroadcast::class;

    public function definition(): array
    {
        return [
            'sent_by_user_id' => User::factory(),
            'message_body' => $this->faker->sentence(),
            'recipient_count' => $this->faker->numberBetween(1, 100),
        ];
    }
}
