<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('caregiver pages load without JavaScript errors', function () {
    $user = createCaregiver();

    $this->actingAs($user);
    session()->put('auth.password_confirmed_at', time());

    visit('/dashboard')
        ->assertSee('Welcome back')
        ->assertNoJavaScriptErrors();

    visit('/jobs')
        ->assertSee('My Jobs')
        ->assertNoJavaScriptErrors();

    visit('/bookings')
        ->assertSee('Available Bookings')
        ->assertNoJavaScriptErrors();

    visit('/settings/security')
        ->assertSee('Update password')
        ->assertNoJavaScriptErrors();

    visit('/settings/appearance')
        ->assertSee('Appearance settings')
        ->assertNoJavaScriptErrors();
});
