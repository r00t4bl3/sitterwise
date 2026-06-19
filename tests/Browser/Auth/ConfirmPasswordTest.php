<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('confirm password page can be viewed', function () {
    $user = User::factory()->create([
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    visit('/user/confirm-password')
        ->assertSee('Confirm your password')
        ->assertNoJavaScriptErrors();
});

test('user can confirm password correctly', function () {
    $user = User::factory()->create([
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    $page = visit('/user/confirm-password');

    fillField($page, '#password', 'password');

    clickElement($page, 'button[data-test="confirm-password-button"]');

    $page->assertPathIs('/dashboard');
});

test('user sees error with incorrect password', function () {
    $user = User::factory()->create([
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    $page = visit('/user/confirm-password');

    fillField($page, '#password', 'wrong-password');

    clickElement($page, 'button[data-test="confirm-password-button"]');

    $page->assertSee('provided password was incorrect');
});
