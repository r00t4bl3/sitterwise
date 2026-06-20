<?php

use App\Models\Booking;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('unauthenticated user is redirected to login when visiting protected page', function () {
    visit('/dashboard')
        ->assertPathIs('/login');
});

test('unauthenticated user is redirected to login for settings pages', function () {
    visit('/settings/profile')
        ->assertPathIs('/login');
});

test('client cannot access caregiver-only route', function () {
    $user = User::factory()->create(['role' => 'client']);
    Client::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    $page = visit('/milestones');

    $page->assertSee('403');
});

test('caregiver cannot access client-only route', function () {
    $booking = Booking::factory()->create();

    $user = User::factory()->create(['role' => 'caregiver']);

    $this->actingAs($user);

    $page = visit('/reviews/'.$booking->ulid);

    $page->assertSee('403');
});

test('non-admin cannot access admin routes', function () {
    $user = User::factory()->create(['role' => 'caregiver']);

    $this->actingAs($user);

    $page = visit('/clients');

    $page->assertSee('403');
});

test('admin cannot access superadmin-only route', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $this->actingAs($user);

    $page = visit('/broadcast-sms');

    $page->assertSee('403');
});

test('superadmin can access admin routes', function () {
    $user = User::factory()->create(['role' => 'super_admin']);

    $this->actingAs($user);

    visit('/clients')
        ->assertSee('Clients');
});
