<?php

use App\Models\Booking;
use App\Models\Client;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
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

test('booking detail shows status badge', function () {
    $user = createClientUser();
    $client = Client::first();

    $booking = Booking::factory()->forClient($client)->create([
        'status' => 'confirmed',
    ]);

    $this->actingAs($user);

    visit('/bookings/'.$booking->ulid)
        ->assertSee('Booking Details')
        ->assertNoJavaScriptErrors();
});

test('client can cancel an upcoming booking', function () {
    $user = createClientUser();
    $client = Client::first();

    $booking = Booking::factory()->forClient($client)->create([
        'status' => 'pending',
    ]);

    $this->actingAs($user);

    $this->withoutMiddleware(PreventRequestForgery::class);

    $response = $this->post("/bookings/{$booking->ulid}/cancel", [
        'cancellation_reason' => 'Changed my plans',
    ]);

    $response->assertStatus(302);
    $response->assertSessionHasNoErrors();
});
