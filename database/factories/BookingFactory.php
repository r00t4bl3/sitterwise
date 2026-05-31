<?php

namespace Database\Factories;

use App\Enums\BookingPaymentStatus;
use App\Enums\BookingStatus;
use App\Enums\ServiceType;
use App\Models\Booking;
use App\Models\BookingGroup;
use App\Models\Caregiver;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        $startDatetime = now()->addDays(rand(1, 30))->setHour(rand(8, 18))->setMinute(0);
        $endDatetime = (clone $startDatetime)->addHours(rand(2, 8));

        return [
            'ulid' => Str::ulid(),
            'booking_group_id' => BookingGroup::factory(),
            'caregiver_id' => Caregiver::query()->exists() && fake()->boolean(60) ? Caregiver::inRandomOrder()->first()?->id : null,
            'availability_id' => null,
            'start_datetime' => $startDatetime,
            'end_datetime' => $endDatetime,
            'status' => BookingStatus::Received->value,
            'total_amount' => rand(50, 200),
            'total_service_amount' => rand(50, 200),
            'charge_to_client_hourly' => 25.00,
            'paid_to_caregiver' => rand(10, 50),
            'payment_status' => BookingPaymentStatus::Pending->value,
            'stripe_payment_intent_id' => null,
            'actual_amount' => null,
            'charge_attempt_count' => 0,
            'last_charge_attempt_at' => null,
        ];
    }

    public function withBookingGroup(?callable $callback = null): static
    {
        return $this->state(fn (array $attributes) => [
            'booking_group_id' => BookingGroup::factory()->when($callback, $callback),
        ]);
    }

    public function forClient(Client|int $client): static
    {
        $clientId = $client instanceof Client ? $client->id : $client;

        return $this->withBookingGroup(fn ($group) => $group->state(['client_id' => $clientId]));
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::Confirmed->value,
            'caregiver_id' => Caregiver::factory(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::Completed->value,
            'caregiver_id' => Caregiver::factory(),
            'payment_status' => BookingPaymentStatus::Paid->value,
        ]);
    }

    public function comped(): static
    {
        return $this->state(fn (array $attributes) => [
            'booking_group_id' => BookingGroup::factory()->state([
                'requires_payment' => false,
                'service_type' => ServiceType::Comped->value,
            ]),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::Pending->value,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::Cancelled->value,
        ]);
    }
}
