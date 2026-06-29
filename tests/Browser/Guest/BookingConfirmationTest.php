<?php

use App\Models\Booking;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('confirmation page shows booking details', function () {
    $user = User::factory()->create(['role' => 'client']);
    $client = Client::factory()->create(['user_id' => $user->id]);
    $booking = Booking::factory()->forClient($client)->create();

    $this->actingAs($user);

    visit('/book/confirmation/'.$booking->ulid->toString())
        ->assertSee('Booking Request Received')
        ->assertSee('received')
        ->assertNoJavaScriptErrors();
});

test('confirmation page shows password setup link', function () {
    $user = User::factory()->create(['role' => 'client']);
    $client = Client::factory()->create(['user_id' => $user->id]);
    $booking = Booking::factory()->forClient($client)->create();

    $this->actingAs($user);

    session()->put('password_setup_token', 'test-token-123');
    session()->put('password_setup_email', $booking->client_email);

    visit('/book/confirmation/'.$booking->ulid->toString())
        ->assertSee('Set Your Password')
        ->assertNoJavaScriptErrors();
});

test('confirmation page shows booking details with status and service type', function () {
    $user = User::factory()->create(['role' => 'client']);
    $client = Client::factory()->create(['user_id' => $user->id]);
    $booking = Booking::factory()->forClient($client)->create();

    $this->actingAs($user);

    visit('/book/confirmation/'.$booking->ulid)
        ->assertSee('Booking #')
        ->assertSee($booking->ulid)
        ->assertSee($booking->status)
        ->assertSee($booking->service_type)
        ->assertNoJavaScriptErrors();
});
