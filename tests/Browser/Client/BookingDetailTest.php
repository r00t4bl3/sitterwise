<?php

use App\Models\Booking;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('client can view booking detail', function () {
    $user = createClientUser();
    $client = Client::first();

    $booking = Booking::factory()->forClient($client)->create();

    $this->actingAs($user);

    visit('/bookings/'.$booking->ulid)
        ->assertNoJavaScriptErrors();
});
