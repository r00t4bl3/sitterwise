<?php

use App\Models\Booking;
use App\Models\Caregiver;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('caregiver dashboard loads without JavaScript errors', function () {
    seedLookupTables();
    $user = createCaregiver();

    $this->actingAs($user);
    session()->put('auth.password_confirmed_at', time());

    visit('/dashboard')
        ->assertSee('Welcome back')
        ->assertNoJavaScriptErrors();
});

test('caregiver bookings index loads without JavaScript errors', function () {
    $user = createCaregiver();

    $this->actingAs($user);
    session()->put('auth.password_confirmed_at', time());

    visit('/bookings')
        ->assertSee('Available Bookings')
        ->assertNoJavaScriptErrors();
});

test('caregiver booking detail loads without JavaScript errors', function () {
    seedLookupTables();
    $user = createCaregiver();
    $caregiver = Caregiver::where('user_id', $user->id)->first();
    $client = Client::factory()->create();
    $booking = Booking::factory()->forClient($client)->create([
        'caregiver_id' => $caregiver->id,
    ]);

    $this->actingAs($user);
    session()->put('auth.password_confirmed_at', time());

    visit('/bookings/'.$booking->ulid)
        ->assertNoJavaScriptErrors();
});

test('caregiver jobs index loads without JavaScript errors', function () {
    $user = createCaregiver();

    $this->actingAs($user);
    session()->put('auth.password_confirmed_at', time());

    visit('/jobs')
        ->assertSee('My Jobs')
        ->assertNoJavaScriptErrors();
});

test('caregiver job detail loads without JavaScript errors', function () {
    seedLookupTables();
    $user = createCaregiver();
    $caregiver = Caregiver::where('user_id', $user->id)->first();
    $client = Client::factory()->create();
    $booking = Booking::factory()->forClient($client)->create([
        'caregiver_id' => $caregiver->id,
    ]);

    $this->actingAs($user);
    session()->put('auth.password_confirmed_at', time());

    visit('/jobs/'.$booking->ulid)
        ->assertNoJavaScriptErrors();
});

test('caregiver settings pages load without JavaScript errors', function () {
    $user = createCaregiver();

    $this->actingAs($user);
    session()->put('auth.password_confirmed_at', time());

    visit('/settings/profile')
        ->assertNoJavaScriptErrors();

    visit('/settings/security')
        ->assertSee('Update password')
        ->assertNoJavaScriptErrors();

    visit('/settings/appearance')
        ->assertSee('Appearance settings')
        ->assertNoJavaScriptErrors();

    visit('/settings/push-notifications')
        ->assertNoJavaScriptErrors();

    visit('/settings/caregiver/pause')
        ->assertNoJavaScriptErrors();
});

test('caregiver payouts page loads without JavaScript errors', function () {
    $user = createCaregiver();

    $this->actingAs($user);
    session()->put('auth.password_confirmed_at', time());

    visit('/payouts')
        ->assertNoJavaScriptErrors();
});

test('caregiver milestones page loads without JavaScript errors', function () {
    $user = createCaregiver();

    $this->actingAs($user);
    session()->put('auth.password_confirmed_at', time());

    visit('/milestones')
        ->assertNoJavaScriptErrors();
});
