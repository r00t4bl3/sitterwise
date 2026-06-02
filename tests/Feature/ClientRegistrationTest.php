<?php

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
});

function validRegistrationData(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'phone' => '(619) 555-1212',
        'email' => 'jane@example.com',
        'password' => 'password123A!',
        'password_confirmation' => 'password123A!',
    ], $overrides);
}

test('registration page renders', function () {
    $this->get('/register')->assertSuccessful();
});

test('happy path creates user and client', function () {
    $response = $this->post('/register', validRegistrationData());

    $response->assertRedirect('/dashboard');

    $user = User::where('email', 'jane@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->role)->toBe('client')
        ->and($user->name)->toBe('Jane Doe');

    $client = Client::where('user_id', $user->id)->first();
    expect($client)->not->toBeNull()
        ->and($client->first_name)->toBe('Jane')
        ->and($client->last_name)->toBe('Doe');
});

test('user is authenticated after registration', function () {
    $this->post('/register', validRegistrationData());

    $this->assertAuthenticated();
});

test('first_name is required', function () {
    $this->post('/register', validRegistrationData(['first_name' => '']))
        ->assertSessionHasErrors('first_name');
});

test('last_name is required', function () {
    $this->post('/register', validRegistrationData(['last_name' => '']))
        ->assertSessionHasErrors('last_name');
});

test('email is required', function () {
    $this->post('/register', validRegistrationData(['email' => '']))
        ->assertSessionHasErrors('email');
});

test('phone is required', function () {
    $this->post('/register', validRegistrationData(['phone' => '']))
        ->assertSessionHasErrors('phone');
});

test('password is required', function () {
    $this->post('/register', validRegistrationData(['password' => '', 'password_confirmation' => '']))
        ->assertSessionHasErrors('password');
});

test('duplicate email is rejected', function () {
    User::factory()->create(['email' => 'jane@example.com']);

    $this->post('/register', validRegistrationData())
        ->assertSessionHasErrors('email');
});

test('password confirmation mismatch is rejected', function () {
    $this->post('/register', validRegistrationData([
        'password' => 'password123A!',
        'password_confirmation' => 'different123A!',
    ]))->assertSessionHasErrors('password');
});

test('phone stored in e164 format', function () {
    $this->post('/register', validRegistrationData());

    $client = Client::where('first_name', 'Jane')->first();
    expect($client->phone)->toBe('+16195551212');
});

test('how_did_you_hear is saved when provided', function () {
    $this->post('/register', validRegistrationData([
        'how_did_you_hear' => 'social_media',
    ]));

    $client = Client::where('first_name', 'Jane')->first();
    expect($client->how_did_you_hear)->toBe('social_media');
});

test('how_did_you_hear is null when omitted', function () {
    $this->post('/register', validRegistrationData());

    $client = Client::where('first_name', 'Jane')->first();
    expect($client->how_did_you_hear)->toBeNull();
});
