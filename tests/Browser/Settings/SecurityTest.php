<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('security settings page can be viewed', function () {
    $user = User::factory()->create([
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);
    session()->put('auth.password_confirmed_at', time());

    visit('/settings/security')
        ->assertSee('Update password')
        ->assertSee('Current password')
        ->assertSee('New password')
        ->assertNoJavaScriptErrors();
});

test('user can update their password', function () {
    $user = User::factory()->create([
        'password' => bcrypt('current-password'),
    ]);

    $this->actingAs($user);
    session()->put('auth.password_confirmed_at', time());

    $page = visit('/settings/security');

    fillField($page, '#current_password', 'current-password');
    fillField($page, '#password', 'new-password');
    fillField($page, '#password_confirmation', 'new-password');
    clickElement($page, 'button[data-test="update-password-button"]');

    $page->assertSee('Saved');

    $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
});

test('user sees error with wrong current password', function () {
    $user = User::factory()->create([
        'password' => bcrypt('current-password'),
    ]);

    $this->actingAs($user);
    session()->put('auth.password_confirmed_at', time());

    $page = visit('/settings/security');

    fillField($page, '#current_password', 'wrong-password');
    fillField($page, '#password', 'new-password');
    fillField($page, '#password_confirmation', 'new-password');
    clickElement($page, 'button[data-test="update-password-button"]');

    $page->assertSee('The password is incorrect.');
});
