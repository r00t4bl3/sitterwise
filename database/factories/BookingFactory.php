<?php

namespace Database\Factories;

use App\Enums\BookingPaymentStatus;
use App\Enums\BookingStatus;
use App\Enums\LocationType;
use App\Enums\ServiceType;
use App\Models\Booking;
use App\Models\BookingGroup;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\ClientAddress;
use App\Models\Hotel;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        $startDatetime = now()->addDays(rand(1, 30))->setHour(rand(8, 18))->setMinute(0);
        $endDatetime = (clone $startDatetime)->addHours(rand(2, 8));

        return [
            'booking_group_id' => BookingGroup::factory(),
            'client_id' => Client::factory(),
            'caregiver_id' => null,
            'availability_id' => null,
            'hotel_id' => null,
            'address_id' => ClientAddress::factory(),
            'service_type' => ServiceType::Babysitter->value,
            'location_type' => LocationType::PrivateHome->value,
            'start_datetime' => $startDatetime,
            'end_datetime' => $endDatetime,
            'status' => BookingStatus::Received->value,
            'special_considerations' => null,
            'caregiver_notes' => null,
            'notes_to_sitterwise' => null,
            'admin_notes' => null,
            'corporate_id' => null,
            'total_amount' => rand(50, 200),
            'payment_status' => BookingPaymentStatus::Pending->value,
            'requires_payment' => true,
        ];
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
            'requires_payment' => false,
            'service_type' => ServiceType::Comped->value,
        ]);
    }

    public function hotel(): static
    {
        return $this->state(fn (array $attributes) => [
            'hotel_id' => Hotel::factory(),
            'address_id' => null,
            'location_type' => LocationType::Hotel->value,
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
