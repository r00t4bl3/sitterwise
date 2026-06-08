<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('super admin pages load without JavaScript errors', function () {
    $user = User::factory()->create(['role' => 'super_admin']);

    $this->actingAs($user);
    session()->put('auth.password_confirmed_at', time());

    visit('/dashboard')
        ->assertSee('SuperAdmin Dashboard')
        ->assertNoJavaScriptErrors();

    visit('/bookings')
        ->assertSee('Bookings')
        ->assertNoJavaScriptErrors();

    visit('/clients')
        ->assertSee('Clients')
        ->assertNoJavaScriptErrors();

    visit('/caregivers')
        ->assertSee('Caregivers')
        ->assertNoJavaScriptErrors();

    visit('/applications')
        ->assertSee('Applications')
        ->assertNoJavaScriptErrors();

    visit('/transactions')
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
