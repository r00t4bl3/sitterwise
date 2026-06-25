<?php

use App\Http\Middleware\TrackEmailCheckStrikes;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\assertGuest;

uses(RefreshDatabase::class);

// --- Blur endpoint ---

test('blur endpoint returns exists false for unknown email', function () {
    $response = $this->postJson('/book/check-email', ['email' => 'unknown@example.com']);

    $response->assertOk()->assertJson(['exists' => false]);
});

test('blur endpoint returns exists true for registered email', function () {
    User::factory()->create(['email' => 'existing@example.com']);

    $response = $this->postJson('/book/check-email', ['email' => 'existing@example.com']);

    $response->assertOk()->assertJson(['exists' => true]);
});

test('blur endpoint validates email format', function () {
    $response = $this->postJson('/book/check-email', ['email' => 'not-an-email']);

    $response->assertStatus(422);
});

// --- Strike tracking middleware ---

test('strike tracking increments cache on 429', function () {
    $middleware = new TrackEmailCheckStrikes;
    $request = Request::create('/_test', 'POST');

    $middleware->handle($request, fn () => response('', 429));

    expect(Cache::get('email-check-strikes:127.0.0.1'))->toBe(1);
});

test('strike tracking decrements cache on success', function () {
    Cache::put('email-check-strikes:127.0.0.1', 3, now()->addDay());
    $middleware = new TrackEmailCheckStrikes;
    $request = Request::create('/_test', 'POST');

    $middleware->handle($request, fn () => response('ok'));

    expect(Cache::get('email-check-strikes:127.0.0.1'))->toBe(2);
});

test('strike tracking never goes below zero', function () {
    Cache::put('email-check-strikes:127.0.0.1', 0, now()->addDay());
    $middleware = new TrackEmailCheckStrikes;
    $request = Request::create('/_test', 'POST');

    $middleware->handle($request, fn () => response('ok'));

    expect(Cache::get('email-check-strikes:127.0.0.1'))->toBe(0);
});

// --- Submit-time safety net ---

test('guest booking form rejects existing email at submit time', function () {
    User::factory()->create(['email' => 'existing@example.com']);

    $start = now()->addDays(1)->setHour(18)->setMinute(0)->setSecond(0);
    $end = (clone $start)->addHours(4);

    $response = $this->post(route('guest.bookings.store'), [
        'client_first_name' => 'John',
        'client_last_name' => 'Guest',
        'client_email' => 'existing@example.com',
        'client_phone' => '+11234567890',
        'service_type' => 'babysitter',
        'location_type' => 'private_home',
        'start_datetime' => $start->toDateTimeString(),
        'end_datetime' => $end->toDateTimeString(),
        'address_line1' => '456 Guest Ln',
        'address_city' => 'San Diego',
        'address_state' => 'CA',
        'address_zip' => '92101',
        'new_children' => [
            ['name' => 'Child 1', 'gender' => 'male', 'birth_month' => '1', 'birth_year' => '2020'],
        ],
        'how_did_you_hear' => 'search_engine',
        'sms_consent' => true,
    ]);

    $response->assertSessionHasErrors(['client_email']);
    assertGuest();
});
