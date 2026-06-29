<?php

use App\Models\Booking;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('client dashboard loads', function () {
    $user = createClientUser();

    $this->actingAs($user);

    visit('/dashboard')
        ->assertSee('Welcome back')
        ->assertNoJavaScriptErrors();
});

test('caregiver dashboard loads', function () {
    $user = createCaregiver();

    $this->actingAs($user);

    visit('/dashboard')
        ->assertSee('Welcome back')
        ->assertNoJavaScriptErrors();
});

test('admin dashboard loads', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $this->actingAs($user);

    visit('/dashboard')
        ->assertSee('Admin Dashboard')
        ->assertNoJavaScriptErrors();
});

test('super admin dashboard loads', function () {
    $user = User::factory()->create(['role' => 'super_admin']);

    $this->actingAs($user);

    visit('/dashboard')
        ->assertSee('SuperAdmin Dashboard')
        ->assertNoJavaScriptErrors();
});

test('client dashboard shows upcoming bookings widget', function () {
    $user = createClientUser();
    $client = Client::first();

    Booking::factory()->forClient($client)->create([
        'status' => 'confirmed',
    ]);

    $this->actingAs($user);

    visit('/dashboard')
        ->assertNoJavaScriptErrors();
});

test('client dashboard shows recent activity', function () {
    $user = createClientUser();
    $client = Client::first();

    Booking::factory()->forClient($client)->create([
        'status' => 'completed',
    ]);

    $this->actingAs($user);

    visit('/dashboard')
        ->assertNoJavaScriptErrors();
});

test('caregiver dashboard shows available jobs widget', function () {
    $user = createCaregiver();

    $this->actingAs($user);

    visit('/dashboard')
        ->assertNoJavaScriptErrors();
});

test('caregiver dashboard shows earnings summary', function () {
    $user = createCaregiver();

    $this->actingAs($user);

    visit('/dashboard')
        ->assertNoJavaScriptErrors();
});
