<?php

use App\Models\Booking;
use App\Models\BookingGroup;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

it('runs dry-run without errors when no client_payments exist', function () {
    $this->artisan('clients:normalize-payment-amounts')
        ->assertExitCode(0);
});

it('runs with client_payments that have bubble_id', function () {
    $user = User::factory()->create(['role' => 'client']);
    $client = Client::factory()->for($user)->create();
    $group = BookingGroup::factory()->for($client)->create();

    $booking = Booking::factory()->create([
        'booking_group_id' => $group->id,
    ]);

    ClientPayment::query()->create([
        'booking_id' => $booking->id,
        'client_id' => $client->id,
        'amount' => 18113.00,
        'status' => 'succeeded',
        'provider' => 'stripe',
        'bubble_id' => 'test_bubble_id_123',
    ]);

    $this->artisan('clients:normalize-payment-amounts')
        ->assertExitCode(0);
});

it('does not modify amounts with --apply', function () {
    $user = User::factory()->create(['role' => 'client']);
    $client = Client::factory()->for($user)->create();
    $group = BookingGroup::factory()->for($client)->create();

    $booking = Booking::factory()->create([
        'booking_group_id' => $group->id,
    ]);

    ClientPayment::query()->create([
        'booking_id' => $booking->id,
        'client_id' => $client->id,
        'amount' => 100.00,
        'status' => 'succeeded',
        'provider' => 'stripe',
        'bubble_id' => 'test_nonexistent',
    ]);

    $this->artisan('clients:normalize-payment-amounts --apply')
        ->assertExitCode(0);

    $payment = ClientPayment::first();
    expect((float) $payment->amount)->toBe(100.00);
});
