<?php

use App\Models\Booking;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('client index pages load without JavaScript errors', function () {
    $user = createClientUser();
    $client = Client::where('user_id', $user->id)->first();
    $booking = Booking::factory()->forClient($client)->create();

    $this->actingAs($user);
    session()->put('auth.password_confirmed_at', time());

    visit('/dashboard')
        ->assertSee('Welcome back')
        ->assertNoJavaScriptErrors();
});

test('client bookings index page loads without JavaScript errors', function () {
    $user = createClientUser();

    $this->actingAs($user);
    session()->put('auth.password_confirmed_at', time());

    visit('/bookings')
        ->assertSee('Bookings')
        ->assertNoJavaScriptErrors();
});

test('client bookings create page loads without JavaScript errors', function () {
    $user = createClientUser();

    $this->actingAs($user);
    session()->put('auth.password_confirmed_at', time());

    visit('/bookings/create')
        ->assertNoJavaScriptErrors();
});

test('client booking detail page loads without JavaScript errors', function () {
    $user = createClientUser();
    $client = Client::where('user_id', $user->id)->first();
    $booking = Booking::factory()->forClient($client)->create();

    $this->actingAs($user);
    session()->put('auth.password_confirmed_at', time());

    visit('/bookings/'.$booking->ulid)
        ->assertNoJavaScriptErrors();
});

test('client settings pages load without JavaScript errors', function () {
    $user = createClientUser();

    $this->actingAs($user);
    session()->put('auth.password_confirmed_at', time());

    visit('/settings/profile')
        ->assertSee('Profile information')
        ->assertNoJavaScriptErrors();

    visit('/settings/security')
        ->assertNoJavaScriptErrors();

    visit('/settings/appearance')
        ->assertSee('Appearance settings')
        ->assertNoJavaScriptErrors();

    visit('/settings/push-notifications')
        ->assertNoJavaScriptErrors();
});
