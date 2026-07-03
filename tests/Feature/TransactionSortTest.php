<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('transactions are ordered by start_datetime desc even when created_at ties', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $client = Client::factory()->create();

    // Same created_at (mimicking a bulk import run), different start_datetime.
    $sharedCreatedAt = Carbon::parse('2026-01-01 00:00:00');

    $older = Booking::factory()->forClient($client)->create([
        'status' => BookingStatus::Completed->value,
        'start_datetime' => Carbon::parse('2026-05-01 09:00:00'),
        'end_datetime' => Carbon::parse('2026-05-01 13:00:00'),
        'created_at' => $sharedCreatedAt,
    ]);
    $newer = Booking::factory()->forClient($client)->create([
        'status' => BookingStatus::Completed->value,
        'start_datetime' => Carbon::parse('2026-06-01 09:00:00'),
        'end_datetime' => Carbon::parse('2026-06-01 13:00:00'),
        'created_at' => $sharedCreatedAt,
    ]);

    $this->actingAs($admin)->get(route('transactions.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('bookings.data.0.id', $newer->id)
            ->where('bookings.data.1.id', $older->id)
        );
});
