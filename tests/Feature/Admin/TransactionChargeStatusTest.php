<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->client = Client::factory()->create();
});

test('transactions report the service charge and tip charge separately (#billing)', function () {
    $booking = Booking::factory()->forClient($this->client)->create([
        'status' => BookingStatus::Completed->value,
        'payment_status' => 'failed',
    ]);
    $booking->forceFill(['charge_attempt_count' => 2])->saveQuietly();

    // Service charge failed (insufficient funds); tip charge succeeded separately.
    ClientPayment::factory()->failed()->create([
        'booking_id' => $booking->id,
        'client_id' => $this->client->id,
        'amount' => 140.00,
        'error_message' => 'Your card has insufficient funds.',
        'metadata' => null,
    ]);
    ClientPayment::factory()->succeeded()->create([
        'booking_id' => $booking->id,
        'client_id' => $this->client->id,
        'amount' => 20.00,
        'metadata' => ['type' => 'tip'],
    ]);

    $this->actingAs($this->admin)->get(route('transactions.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('bookings.data.0.id', $booking->id)
            ->where('bookings.data.0.charge.service_state', 'failed')
            ->where('bookings.data.0.charge.service_error', fn ($e) => str_contains((string) $e, 'insufficient funds'))
            ->where('bookings.data.0.charge.attempt_count', 2)
            ->where('bookings.data.0.charge.tip_state', 'succeeded')
            ->where('bookings.data.0.charge.tip_amount', fn ($a) => (float) $a === 20.0)
        );
});

test('a charged booking reports the service charge as succeeded (#billing)', function () {
    $booking = Booking::factory()->forClient($this->client)->create([
        'status' => BookingStatus::Paid->value,
        'payment_status' => 'charged',
    ]);

    ClientPayment::factory()->succeeded()->create([
        'booking_id' => $booking->id,
        'client_id' => $this->client->id,
        'amount' => 140.00,
        'metadata' => null,
    ]);

    $this->actingAs($this->admin)->get(route('transactions.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('bookings.data.0.id', $booking->id)
            ->where('bookings.data.0.charge.service_state', 'succeeded')
            ->where('bookings.data.0.charge.service_error', null)
        );
});
