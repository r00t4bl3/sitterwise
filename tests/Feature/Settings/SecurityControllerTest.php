<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
});

test('security page requires password confirmation', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $response = $this->actingAs($user)->get('/settings/security');

    // Redirects to password confirmation page
    $response->assertRedirect();
});

test('password can be updated', function () {
    $user = User::factory()->create([
        'password' => Hash::make('old-password'),
    ]);

    $response = $this->actingAs($user)->put('/settings/password', [
        'current_password' => 'old-password',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ]);

    $response->assertRedirect();
    $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
});

test('current password must be correct', function () {
    $user = User::factory()->create([
        'password' => Hash::make('correct-password'),
    ]);

    $response = $this->actingAs($user)->put('/settings/password', [
        'current_password' => 'wrong-password',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ]);

    $response->assertSessionHasErrors('current_password');
});

test('new password requires confirmation', function () {
    $user = User::factory()->create([
        'password' => Hash::make('old-password'),
    ]);

    $response = $this->actingAs($user)->put('/settings/password', [
        'current_password' => 'old-password',
        'password' => 'new-password-123',
        'password_confirmation' => 'different-password',
    ]);

    $response->assertSessionHasErrors('password');
});

test('unauthenticated user is redirected from security', function () {
    $response = $this->get('/settings/security');

    $response->assertRedirect('/login');
});
