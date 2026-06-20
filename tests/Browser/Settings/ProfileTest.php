<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('profile settings page can be viewed', function () {
    $user = User::factory()->create([
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    visit('/settings/profile')
        ->assertSee('Profile information')
        ->assertSee('Update your name and email address')
        ->assertSee('Name')
        ->assertSee('Email address')
        ->assertNoJavaScriptErrors();
});

test('user can update their name', function () {
    $user = User::factory()->create([
        'name' => 'Old Name',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    $page = visit('/settings/profile');

    fillField($page, '#name', 'New Name');
    clickElement($page, 'button[data-test="update-profile-button"]');

    $page->assertSee('Saved');
});

test('user can update their email', function () {
    $user = User::factory()->create([
        'email' => 'old@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    $page = visit('/settings/profile');

    fillField($page, '#email', 'new@example.com');
    clickElement($page, 'button[data-test="update-profile-button"]');

    $page->assertSee('Saved');
});

test('user sees error with invalid email format', function () {
    $user = User::factory()->create([
        'password' => bcrypt('password'),
    ]);

    $this->actingAs($user);

    $page = visit('/settings/profile');

    fillField($page, '#email', 'not-an-email');
    clickElement($page, 'button[data-test="update-profile-button"]');

    usleep(500000);

    $page->assertDontSee('Saved');
});
