<?php

namespace Database\Factories;

use App\Models\BroadcastMessage;
use App\Models\Caregiver;
use App\Models\SmsBroadcast;
use Illuminate\Database\Eloquent\Factories\Factory;

class BroadcastMessageFactory extends Factory
{
    protected $model = BroadcastMessage::class;

    public function definition(): array
    {
        return [
            'broadcast_id' => SmsBroadcast::factory(),
            'caregiver_id' => Caregiver::factory(),
            'phone_number' => $this->faker->phoneNumber(),
            'message_body' => $this->faker->sentence(),
            'twilio_message_sid' => 'SM'.$this->faker->unique()->numerify('##########'),
            'status' => 'queued',
            'sent_at' => null,
        ];
    }
}
