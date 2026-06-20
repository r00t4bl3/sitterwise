<?php

use App\Models\Caregiver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('login page loads without JavaScript errors', function () {
    visit('/login')
        ->assertSee('Log in to your account')
        ->assertNoJavaScriptErrors();
});

test('forgot password page loads without JavaScript errors', function () {
    visit('/forgot-password')
        ->assertSee('Forgot password')
        ->assertNoJavaScriptErrors();
});

test('register page loads without JavaScript errors', function () {
    visit('/register')
        ->assertSee('Create an account')
        ->assertNoJavaScriptErrors();
});

test('booking create page loads without JavaScript errors', function () {
    visit('/book')
        ->assertSee("It's you!")
        ->assertNoJavaScriptErrors();
});

test('caregiver bio page loads without JavaScript errors', function () {
    seedLookupTables();
    $user = User::factory()->create(['role' => 'caregiver']);
    $caregiver = Caregiver::factory()->create([
        'user_id' => $user->id,
        'slug' => 'test-caregiver-smoke',
        'status' => 'active',
    ]);

    visit('/bio/test-caregiver-smoke')
        ->assertNoJavaScriptErrors();
});

test('caregiver apply verify email page loads without JavaScript errors', function () {
    visit('/caregiver/apply/verify-email')
        ->assertNoJavaScriptErrors();
});

test('caregiver apply thank you page loads without JavaScript errors', function () {
    visit('/caregiver/apply/thank-you')
        ->assertNoJavaScriptErrors();
});

test('caregiver apply status page loads without JavaScript errors', function () {
    seedLookupTables();
    $user = User::factory()->create(['role' => 'caregiver']);
    $caregiver = Caregiver::factory()->create([
        'user_id' => $user->id,
        'status_token' => 'test-status-token-12345',
    ]);

    visit('/caregiver/apply/status/test-status-token-12345')
        ->assertNoJavaScriptErrors();
});
