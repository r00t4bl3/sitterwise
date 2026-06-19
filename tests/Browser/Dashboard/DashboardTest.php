<?php

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
