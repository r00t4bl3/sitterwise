<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('caregiver available bookings index can be viewed', function () {
    $user = createCaregiver();

    $this->actingAs($user);

    visit('/bookings')
        ->assertSee('Available Bookings')
        ->assertSee('No available bookings')
        ->assertNoJavaScriptErrors();
});
