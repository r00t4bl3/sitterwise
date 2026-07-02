<?php

use App\Models\Booking;
use App\Models\BookingGroup;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\ClientPaymentMethod;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

it('backfills payment_method_id using default method', function () {
    $user = User::factory()->create(['role' => 'client']);
    $client = Client::factory()->for($user)->create();
    $group = BookingGroup::factory()->for($client)->create();
    $booking = Booking::factory()->create(['booking_group_id' => $group->id]);

    $defaultMethod = ClientPaymentMethod::factory()->create([
        'client_id' => $client->id,
        'is_default' => true,
    ]);

    $payment = ClientPayment::query()->create([
        'booking_id' => $booking->id,
        'client_id' => $client->id,
        'amount' => 100.00,
        'status' => 'succeeded',
        'provider' => 'stripe',
        'payment_method_id' => null,
    ]);

    $this->artisan('clients:backfill-payment-methods')->assertExitCode(0);

    $payment->refresh();
    expect($payment->payment_method_id)->toBe($defaultMethod->id);
});

it('falls back to latest method when no default exists', function () {
    $user = User::factory()->create(['role' => 'client']);
    $client = Client::factory()->for($user)->create();
    $group = BookingGroup::factory()->for($client)->create();
    $booking = Booking::factory()->create(['booking_group_id' => $group->id]);

    $olderMethod = ClientPaymentMethod::factory()->create([
        'client_id' => $client->id,
        'is_default' => false,
        'created_at' => now()->subDay(),
    ]);

    $latestMethod = ClientPaymentMethod::factory()->create([
        'client_id' => $client->id,
        'is_default' => false,
        'created_at' => now(),
    ]);

    $payment = ClientPayment::query()->create([
        'booking_id' => $booking->id,
        'client_id' => $client->id,
        'amount' => 100.00,
        'status' => 'succeeded',
        'provider' => 'stripe',
        'payment_method_id' => null,
    ]);

    $this->artisan('clients:backfill-payment-methods')->assertExitCode(0);

    $payment->refresh();
    expect($payment->payment_method_id)->toBe($latestMethod->id);
});

it('skips clients with no payment methods', function () {
    $user = User::factory()->create(['role' => 'client']);
    $client = Client::factory()->for($user)->create();
    $group = BookingGroup::factory()->for($client)->create();
    $booking = Booking::factory()->create(['booking_group_id' => $group->id]);

    $payment = ClientPayment::query()->create([
        'booking_id' => $booking->id,
        'client_id' => $client->id,
        'amount' => 100.00,
        'status' => 'succeeded',
        'provider' => 'stripe',
        'payment_method_id' => null,
    ]);

    $this->artisan('clients:backfill-payment-methods')->assertExitCode(0);

    $payment->refresh();
    expect($payment->payment_method_id)->toBeNull();
});

it('does not overwrite existing payment_method_id', function () {
    $user = User::factory()->create(['role' => 'client']);
    $client = Client::factory()->for($user)->create();
    $group = BookingGroup::factory()->for($client)->create();
    $booking = Booking::factory()->create(['booking_group_id' => $group->id]);

    $method = ClientPaymentMethod::factory()->create([
        'client_id' => $client->id,
        'is_default' => true,
    ]);

    $otherMethod = ClientPaymentMethod::factory()->create([
        'client_id' => $client->id,
        'is_default' => false,
    ]);

    $payment = ClientPayment::query()->create([
        'booking_id' => $booking->id,
        'client_id' => $client->id,
        'amount' => 100.00,
        'status' => 'succeeded',
        'provider' => 'stripe',
        'payment_method_id' => $otherMethod->id,
    ]);

    $this->artisan('clients:backfill-payment-methods')->assertExitCode(0);

    $payment->refresh();
    expect($payment->payment_method_id)->toBe($otherMethod->id);
});

it('updates multiple payments for the same client', function () {
    $user = User::factory()->create(['role' => 'client']);
    $client = Client::factory()->for($user)->create();

    $defaultMethod = ClientPaymentMethod::factory()->create([
        'client_id' => $client->id,
        'is_default' => true,
    ]);

    $payments = [];
    for ($i = 0; $i < 3; $i++) {
        $group = BookingGroup::factory()->for($client)->create();
        $booking = Booking::factory()->create(['booking_group_id' => $group->id]);

        $payments[] = ClientPayment::query()->create([
            'booking_id' => $booking->id,
            'client_id' => $client->id,
            'amount' => 100.00,
            'status' => 'succeeded',
            'provider' => 'stripe',
            'payment_method_id' => null,
        ]);
    }

    $this->artisan('clients:backfill-payment-methods')->assertExitCode(0);

    foreach ($payments as $payment) {
        $payment->refresh();
        expect($payment->payment_method_id)->toBe($defaultMethod->id);
    }
});

it('reports success when no payments to update', function () {
    $this->artisan('clients:backfill-payment-methods')->assertExitCode(0);
});
