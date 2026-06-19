<?php

use App\Models\Caregiver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('caregiver can view pause account page', function () {
    $user = createCaregiver();

    $this->actingAs($user);

    visit('/settings/caregiver/pause')
        ->assertSee('Pause your account')
        ->assertNoJavaScriptErrors();
});

test('paused caregiver can view resume page', function () {
    $user = User::factory()->create(['role' => 'caregiver']);

    Caregiver::create([
        'user_id' => $user->id,
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'slug' => 'jane-smith-paused',
        'phone' => '555-123-4567',
        'address_line1' => '123 Main St',
        'address_city' => 'San Diego',
        'address_state' => 'CA',
        'address_zip' => '92101',
        'date_of_birth' => '1990-01-01',
        'status' => 'on_hold',
    ]);

    $this->actingAs($user);

    visit('/settings/caregiver/pause')
        ->assertSee("You're on a break")
        ->assertNoJavaScriptErrors();
});
