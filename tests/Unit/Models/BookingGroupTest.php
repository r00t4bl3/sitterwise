<?php

use App\Models\Booking;
use App\Models\BookingGroup;
use App\Models\Client;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('can be instantiated', function () {
    $bookingGroup = BookingGroup::factory()->make();

    $this->assertInstanceOf(BookingGroup::class, $bookingGroup);
});

test('has correct fillable fields', function () {
    $client = Client::factory()->create();
    $bookingGroup = BookingGroup::factory()->create([
        'client_id' => $client->id,
        'submitted_at' => now(),
        'submission_type' => 'logged_in',
        'is_split' => true,
    ]);

    $this->assertEquals($client->id, $bookingGroup->client_id);
    $this->assertInstanceOf(CarbonImmutable::class, $bookingGroup->submitted_at);
    $this->assertEquals('logged_in', $bookingGroup->submission_type);
    $this->assertTrue($bookingGroup->is_split);
});

test('casts submitted at as datetime', function () {
    $bookingGroup = BookingGroup::factory()->create([
        'submitted_at' => '2026-12-25 10:30:00',
    ]);

    $this->assertInstanceOf(CarbonImmutable::class, $bookingGroup->submitted_at);
    $this->assertEquals('2026-12-25', $bookingGroup->submitted_at->toDateString());
});

test('casts is split as boolean', function () {
    $bookingGroup = BookingGroup::factory()->create(['is_split' => true]);

    $this->assertTrue($bookingGroup->is_split);
    $this->assertIsBool($bookingGroup->is_split);
});

test('defines client relationship', function () {
    $client = Client::factory()->create();
    $bookingGroup = BookingGroup::factory()->create(['client_id' => $client->id]);

    $this->assertInstanceOf(Client::class, $bookingGroup->client);
    $this->assertEquals($client->id, $bookingGroup->client->id);
});

test('defines bookings relationship', function () {
    $bookingGroup = BookingGroup::factory()->create();
    $booking = Booking::factory()->create(['booking_group_id' => $bookingGroup->id]);

    $this->assertTrue($bookingGroup->bookings->contains($booking));
});
