<?php

use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

beforeEach(function () {
    Notification::fake();
    $this->skipUnlessFortifyFeature(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register as client', function () {
    $response = $this->post(route('register.store'), [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'phone' => '+15551234567',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();

    $user = User::where('email', 'test@example.com')->first();
    expect($user->role)->toBe('client');

    $client = $user->client;
    expect($client)->not->toBeNull();
    expect($client->first_name)->toBe('John');
    expect($client->last_name)->toBe('Doe');
    expect($client->phone)->toBe('+15551234567');
});

test('new users can register with how_did_you_hear', function () {
    $response = $this->post(route('register.store'), [
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'phone' => '+15559876543',
        'email' => 'jane@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'how_did_you_hear' => 'google',
    ]);

    $this->assertAuthenticated();

    $user = User::where('email', 'jane@example.com')->first();
    $client = $user->client;
    expect($client->how_did_you_hear)->toBe('google');
});

test('registration requires required fields', function () {
    $response = $this->post(route('register.store'), [
        'first_name' => '',
        'last_name' => '',
        'phone' => '',
        'email' => '',
        'password' => '',
        'password_confirmation' => '',
    ]);

    $response->assertSessionHasErrors(['first_name', 'last_name', 'phone', 'email', 'password']);
});
