<?php

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
});

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/settings/profile');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('settings/profile')
    );
});

test('profile information can be updated', function () {
    $client = Client::factory()->create();
    $user = $client->user;

    $response = $this->actingAs($user)->patch('/settings/profile', [
        'first_name' => 'Updated',
        'last_name' => 'Name',
        'email' => 'updated@example.com',
    ]);

    $response->assertRedirect('/settings/profile');

    $user->refresh();

    $this->assertEquals('Updated', $user->client->first_name);
    $this->assertEquals('Name', $user->client->last_name);
    $this->assertEquals('updated@example.com', $user->email);
    $this->assertNull($user->email_verified_at);
});

test('email verification status is unchanged when email is unchanged', function () {
    $client = Client::factory()->create();
    $user = $client->user;
    $user->update(['email_verified_at' => now()]);

    $response = $this->actingAs($user)->patch('/settings/profile', [
        'first_name' => $user->client->first_name,
        'last_name' => $user->client->last_name,
        'email' => $user->email,
    ]);

    $response->assertRedirect('/settings/profile');

    $this->assertNotNull($user->refresh()->email_verified_at);
});

test('profile requires first name', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->patch('/settings/profile', [
        'first_name' => '',
        'last_name' => 'Name',
        'email' => $user->email,
    ]);

    $response->assertSessionHasErrors('first_name');
});

test('profile requires last name', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->patch('/settings/profile', [
        'first_name' => 'First',
        'last_name' => '',
        'email' => $user->email,
    ]);

    $response->assertSessionHasErrors('last_name');
});

test('profile requires valid email', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->patch('/settings/profile', [
        'first_name' => 'First',
        'last_name' => 'Last',
        'email' => 'not-an-email',
    ]);

    $response->assertSessionHasErrors('email');
});

test('user can delete their account', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $response = $this->actingAs($user)->delete('/settings/profile', [
        'password' => 'password',
    ]);

    $response->assertRedirect('/');
    $this->assertSoftDeleted('users', ['id' => $user->id]);
    $this->assertGuest();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $response = $this->actingAs($user)->delete('/settings/profile', [
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors('password');
    $this->assertNotNull($user->fresh());
});

test('unauthenticated user is redirected from profile', function () {
    $response = $this->get('/settings/profile');

    $response->assertRedirect('/login');
});
