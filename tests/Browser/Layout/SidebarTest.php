<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('client sees client sidebar nav items', function () {
    $user = createClientUser();

    $this->actingAs($user);

    visit('/dashboard')
        ->assertSee('Dashboard')
        ->assertSee('Bookings')
        ->assertSee('Payments');
});

test('caregiver sees caregiver sidebar nav items', function () {
    $user = createCaregiver();

    $this->actingAs($user);

    visit('/dashboard')
        ->assertSee('My Jobs')
        ->assertSee('Available Jobs');
});

test('admin sees admin sidebar nav items', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $this->actingAs($user);

    visit('/dashboard')
        ->assertSee('Clients')
        ->assertSee('Caregivers')
        ->assertSee('Bookings');
});

test('super admin sees super admin sidebar nav items', function () {
    $user = User::factory()->create(['role' => 'super_admin']);

    $this->actingAs($user);

    visit('/dashboard')
        ->assertSee('Certifications')
        ->assertSee('Specialties')
        ->assertSee('Hotels');
});
