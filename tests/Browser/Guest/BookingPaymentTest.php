<?php

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('payment page redirects to create without session data', function () {
    visit('/book/payment/invalid-token')
        ->assertSee("It's you!")
        ->assertNoJavaScriptErrors();
});

test('successful booking post redirects to payment URL', function () {
    $tomorrow = now()->addDay()->startOfDay()->addHours(9);
    $end = $tomorrow->copy()->addHours(4);

    $this->withoutMiddleware(PreventRequestForgery::class);

    $response = $this->post('/book', [
        'client_first_name' => 'John',
        'client_last_name' => 'Doe',
        'client_email' => 'john@example.com',
        'client_phone' => '+15551234567',
        'service_type' => 'babysitter',
        'location_type' => 'hotel',
        'start_datetime' => $tomorrow->format('Y-m-d\TH:i'),
        'end_datetime' => $end->format('Y-m-d\TH:i'),
        'dates' => [
            ['start_datetime' => $tomorrow->format('Y-m-d\TH:i'), 'end_datetime' => $end->format('Y-m-d\TH:i')],
        ],
        'hotel_name' => 'Test Hotel',
        'how_did_you_hear' => 'google',
        'new_children' => [
            ['name' => 'Test Child', 'gender' => '', 'birth_month' => '', 'birth_year' => ''],
        ],
        'new_pets' => [],
        'sitter_preferences' => [],
        'sms_consent' => true,
    ]);

    $response->assertRedirect();
    $redirectUrl = $response->headers->get('Location');
    expect($redirectUrl)->toMatch('/\/book\/payment\//');
});

test('guest visits payment page with valid session', function () {
    $token = (string) Str::ulid();
    $tomorrow = now()->addDay()->startOfDay()->addHours(9);
    $end = $tomorrow->copy()->addHours(4);

    session()->put('guest_booking_pending', [
        'client_first_name' => 'John',
        'client_last_name' => 'Doe',
        'client_email' => 'john@example.com',
        'client_phone' => '+15551234567',
        'service_type' => 'babysitter',
        'location_type' => 'hotel',
        'start_datetime' => $tomorrow->toIso8601String(),
        'end_datetime' => $end->toIso8601String(),
        'address_line1' => null,
        'address_city' => null,
        'address_state' => null,
        'address_zip' => null,
        'hotel_name' => 'Test Hotel',
        'sms_consent' => true,
        'dates' => [
            [
                'start_datetime' => $tomorrow->toIso8601String(),
                'end_datetime' => $end->toIso8601String(),
            ],
        ],
    ]);
    session()->put('guest_booking_payment_token', $token);

    visit('/book/payment/'.$token)
        ->assertSee('Complete Your Booking')
        ->assertSee('Booking Summary')
        ->assertSee('Payment Details')
        ->assertSee('Payment')
        ->assertNoJavaScriptErrors();
});

test('payment page shows booking progress step 2', function () {
    $token = (string) Str::ulid();
    $tomorrow = now()->addDay()->startOfDay()->addHours(9);
    $end = $tomorrow->copy()->addHours(4);

    session()->put('guest_booking_pending', [
        'client_first_name' => 'John',
        'client_last_name' => 'Doe',
        'client_email' => 'john@example.com',
        'client_phone' => '+15551234567',
        'service_type' => 'babysitter',
        'location_type' => 'hotel',
        'start_datetime' => $tomorrow->toIso8601String(),
        'end_datetime' => $end->toIso8601String(),
        'address_line1' => null,
        'address_city' => null,
        'address_state' => null,
        'address_zip' => null,
        'hotel_name' => 'Test Hotel',
        'sms_consent' => true,
        'dates' => [
            [
                'start_datetime' => $tomorrow->toIso8601String(),
                'end_datetime' => $end->toIso8601String(),
            ],
        ],
    ]);
    session()->put('guest_booking_payment_token', $token);

    visit('/book/payment/'.$token)
        ->assertSee('Complete Your Booking')
        ->assertNoJavaScriptErrors();
});
