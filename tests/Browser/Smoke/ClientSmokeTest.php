<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('client pages load without JavaScript errors', function () {
    $user = createClientUser();

    $this->actingAs($user);
    session()->put('auth.password_confirmed_at', time());

    visit('/dashboard')
        ->assertSee('Welcome back')
        ->assertNoJavaScriptErrors();

    visit('/bookings')
        ->assertSee('Bookings')
        ->assertNoJavaScriptErrors();

    visit('/bookings/create')
        ->assertNoJavaScriptErrors();

    visit('/settings/profile')
        ->assertSee('Profile information')
        ->assertNoJavaScriptErrors();

    visit('/settings/security')
        ->assertSee('Update password')
        ->assertNoJavaScriptErrors();

    visit('/settings/appearance')
        ->assertSee('Appearance settings')
        ->assertNoJavaScriptErrors();
});
