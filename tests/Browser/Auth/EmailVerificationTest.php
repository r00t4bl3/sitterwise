<?php

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('unverified user is prompted to verify email', function () {
    $user = User::factory()->unverified()->create([
        'email' => 'john@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    visit('/email/verify')
        ->assertSee('Verify email')
        ->assertSee('Resend verification email')
        ->assertNoJavaScriptErrors();
});

test('verified user can access dashboard', function () {
    $user = User::factory()->create([
        'email' => 'john@example.com',
        'password' => bcrypt('password'),
    ]);

    Client::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    visit('/dashboard')
        ->assertSee('Welcome back')
        ->assertNoJavaScriptErrors();
});
