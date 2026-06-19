<?php

use App\Enums\LocationType;
use App\Enums\ServiceType;
use App\Models\Booking;
use App\Models\Client;
use App\Models\ClientPet;
use App\Models\PricingRule;
use App\Models\User;
use App\Services\Booking\GuestBookingService;
use Carbon\Carbon;
use Database\Seeders\PricingRulesTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PricingRulesTableSeeder::class);
});

describe('Guest Booking Workflow', function () {
    test('a guest can start a booking and it is persisted in session', function () {
        $start = now()->addDays(1)->setHour(18)->setMinute(0)->setSecond(0);
        $end = (clone $start)->addHours(4);

        $guestData = [
            'client_first_name' => 'John',
            'client_last_name' => 'Guest',
            'client_email' => 'john.guest@example.com',
            'client_phone' => '+11234567890',
            'service_type' => ServiceType::Babysitter->value,
            'location_type' => LocationType::PrivateHome->value,
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
        ];

        $response = $this->post(route('guest.bookings.store'), $guestData);

        $response->assertRedirect();
        expect(Session::has('guest_booking_pending'))->toBeTrue();
        expect(Session::get('guest_booking_pending')['client_email'])->toBe('john.guest@example.com');
    });

    test('a guest booking is completed after payment simulation', function () {
        $start = now()->addDays(1)->setHour(18)->setMinute(0)->setSecond(0);
        $end = (clone $start)->addHours(4);

        $pendingData = [
            'client_first_name' => 'Jane',
            'client_last_name' => 'Guest',
            'client_email' => 'jane.guest@example.com',
            'client_phone' => '+19876543210',
            'service_type' => ServiceType::Babysitter->value,
            'location_type' => LocationType::PrivateHome->value,
            'start_datetime' => $start->toDateTimeString(),
            'end_datetime' => $end->toDateTimeString(),
            'address_line1' => '789 Guest Blvd',
            'address_city' => 'San Diego',
            'address_state' => 'CA',
            'address_zip' => '92101',
            'new_children' => [
                ['name' => 'Kid 1', 'gender' => 'female', 'birth_month' => '5', 'birth_year' => '2021'],
            ],
            'sms_consent' => true,
        ];

        // Setup PricingRule
        PricingRule::create([
            'service_type' => ServiceType::Babysitter->value,
            'number_of_children' => 0,
            'is_for_pets' => false,
            'charge_to_client' => 30.00,
            'paid_to_caregiver' => 20.00,
            'sitterwise_cut' => 10.00,
            'payment_form' => 'stripe',
        ]);

        // Mock the service to bypass real Stripe calls in protected attachPaymentMethod
        $mockService = mock(GuestBookingService::class)->makePartial();
        $mockService->shouldAllowMockingProtectedMethods();
        $mockService->shouldReceive('attachPaymentMethod')->andReturnNull();

        $booking = $mockService->createBookingWithPayment($pendingData, 'pm_test_token');

        expect($booking)->toBeInstanceOf(Booking::class);
        expect($booking->status)->toBe('received');
        expect($booking->client_email)->toBe('jane.guest@example.com');

        $user = User::where('email', 'jane.guest@example.com')->first();
        expect($user)->not->toBeNull();
        expect($user->role)->toBe('client');

        $client = Client::where('user_id', $user->id)->first();
        expect($client)->not->toBeNull();
        expect($client->first_name)->toBe('Jane');

        $this->assertDatabaseHas('client_children', [
            'client_id' => $client->id,
            'name' => 'Kid 1',
        ]);
    });

    test('children and pets are saved to both client_{children,pets} tables and bookings JSON columns', function () {
        $start = now()->addDays(1)->setHour(18)->setMinute(0)->setSecond(0);
        $end = (clone $start)->addHours(4);

        $pendingData = [
            'client_first_name' => 'Sarah',
            'client_last_name' => 'Test',
            'client_email' => 'sarah.test@example.com',
            'client_phone' => '+15551234567',
            'service_type' => ServiceType::Babysitter->value,
            'location_type' => LocationType::PrivateHome->value,
            'start_datetime' => $start->toDateTimeString(),
            'end_datetime' => $end->toDateTimeString(),
            'address_line1' => '123 Test St',
            'address_city' => 'San Diego',
            'address_state' => 'CA',
            'address_zip' => '92101',
            'new_children' => [
                ['name' => 'Emma', 'gender' => 'female', 'birth_month' => '3', 'birth_year' => '2021'],
                ['name' => 'Liam', 'gender' => 'male', 'birth_month' => '7', 'birth_year' => '2024'],
            ],
            'new_pets' => [
                ['name' => 'Buddy', 'type' => 'dog', 'breed' => 'Golden Retriever', 'notes' => 'Friendly'],
                ['name' => 'Whiskers', 'type' => 'cat', 'breed' => '', 'notes' => 'Indoor only'],
            ],
            'sms_consent' => true,
        ];

        $mockService = mock(GuestBookingService::class)->makePartial();
        $mockService->shouldAllowMockingProtectedMethods();
        $mockService->shouldReceive('attachPaymentMethod')->andReturnNull();

        $booking = $mockService->createBookingWithPayment($pendingData, 'pm_test_token');

        $user = User::where('email', 'sarah.test@example.com')->first();
        $client = Client::where('user_id', $user->id)->first();

        // Assert ClientChild records exist
        $this->assertDatabaseHas('client_children', [
            'client_id' => $client->id,
            'name' => 'Emma',
            'gender' => 'female',
        ]);
        $this->assertDatabaseHas('client_children', [
            'client_id' => $client->id,
            'name' => 'Liam',
            'gender' => 'male',
        ]);

        // Assert ClientPet records exist
        $this->assertDatabaseHas('client_pets', [
            'client_id' => $client->id,
            'name' => 'Buddy',
            'type' => 'dog',
        ]);
        $this->assertDatabaseHas('client_pets', [
            'client_id' => $client->id,
            'name' => 'Whiskers',
            'type' => 'cat',
        ]);

        // Reload booking with fresh data
        $booking->refresh();

        // Assert children JSON on booking
        expect($booking->children)->toBeArray();
        expect($booking->children)->toHaveCount(2);
        expect($booking->children[0]['name'])->toBe('Emma');
        expect($booking->children[0]['gender'])->toBe('female');

        $expectedBirthDate = Carbon::createFromDate(2021, 3, 1)->format('Y-m-d');
        expect($booking->children[0]['birth_date'])->toBe($expectedBirthDate);

        expect($booking->children[1]['name'])->toBe('Liam');
        expect($booking->children[1]['gender'])->toBe('male');

        // Assert pets JSON on booking
        expect($booking->pets)->toBeArray();
        expect($booking->pets)->toHaveCount(2);
        expect($booking->pets[0]['name'])->toBe('Buddy');
        expect($booking->pets[0]['type'])->toBe('dog');
        expect($booking->pets[0]['breed'])->toBe('Golden Retriever');
        expect($booking->pets[1]['name'])->toBe('Whiskers');
        expect($booking->pets[1]['type'])->toBe('cat');
    });

    test('baseline: timezone behavior documents current PT-as-UTC storage', function () {
        // The frontend sends naive datetime strings like "2026-05-28T09:00"
        // from formatDateTimeLocal(). These represent 9:00 AM PT but have
        // no timezone indicator. The backend currently treats them as UTC.
        //
        // This test documents the CURRENT (buggy) behavior so we can
        // validate the overhaul later.

        $pendingData = [
            'client_first_name' => 'Timezone',
            'client_last_name' => 'Test',
            'client_email' => 'tz.test@example.com',
            'client_phone' => '+15550000000',
            'service_type' => ServiceType::Babysitter->value,
            'location_type' => LocationType::PrivateHome->value,
            'start_datetime' => '2026-05-28T09:00',
            'end_datetime' => '2026-05-28T13:00',
            'address_line1' => '100 Timezone St',
            'address_city' => 'San Diego',
            'address_state' => 'CA',
            'address_zip' => '92101',
            'new_children' => [
                ['name' => 'TZ Kid', 'gender' => 'male', 'birth_month' => '6', 'birth_year' => '2022'],
            ],
            'sms_consent' => true,
        ];

        PricingRule::create([
            'service_type' => ServiceType::Babysitter->value,
            'number_of_children' => 0,
            'is_for_pets' => false,
            'charge_to_client' => 30.00,
            'paid_to_caregiver' => 20.00,
            'sitterwise_cut' => 10.00,
            'payment_form' => 'stripe',
        ]);

        $mockService = mock(GuestBookingService::class)->makePartial();
        $mockService->shouldAllowMockingProtectedMethods();
        $mockService->shouldReceive('attachPaymentMethod')->andReturnNull();

        $booking = $mockService->createBookingWithPayment($pendingData, 'pm_test_token');
        $booking->refresh();

        // ── 1. Raw DB value ──
        // The frontend sends "09:00" (9 AM PT, no timezone).
        // Mutator converts PT→UTC: 09:00 PT = 16:00 UTC.
        $rawStart = $booking->getRawOriginal('start_datetime');
        $rawEnd = $booking->getRawOriginal('end_datetime');

        // SQLite stores as text; MySQL timestamp stores as UTC.
        // Mutator converts PT→UTC so stored values are correct UTC.
        expect(Carbon::parse($rawStart)->format('H:i'))->toBe('16:00');
        expect(Carbon::parse($rawEnd)->format('H:i'))->toBe('20:00');

        // ── 2. Carbon serialization (what the frontend receives) ──
        // The frontend receives the Carbon instance via jsonSerialize
        // which produces ISO 8601 with Z suffix: "2026-05-28T16:00:00.000000Z"
        $isoStart = $booking->start_datetime->toISOString();
        $isoEnd = $booking->end_datetime->toISOString();

        expect($isoStart)->toMatch('/^2026-05-28T16:00:00\.\d+Z$/');
        expect($isoEnd)->toMatch('/^2026-05-28T20:00:00\.\d+Z$/');

        // ── 3. PT conversion (now shows correct PT time) ──
        // If we convert the stored UTC value to America/Los_Angeles,
        // 16:00 UTC becomes 09:00 AM PT (UTC-7 in May).
        // This is CORRECT — matches what the user entered.
        $ptStart = $booking->start_datetime->copy()->setTimezone('America/Los_Angeles');
        $ptEnd = $booking->end_datetime->copy()->setTimezone('America/Los_Angeles');

        // 16:00 UTC → 09:00 AM PT ✓
        expect($ptStart->format('H:i'))->toBe('09:00');
        expect($ptEnd->format('H:i'))->toBe('13:00');

        // ── 4. Inertia response (what the frontend receives) ──
        // Hit the confirmation endpoint and verify the serialized datetime values.
        $response = $this->get(route('guest.bookings.confirmation', $booking->ulid));
        $response->assertInertia(fn ($page) => $page
            ->where('booking.start_datetime', $booking->start_datetime->toISOString())
            ->where('booking.end_datetime', $booking->end_datetime->toISOString())
        );

        // ── Summary ──
        // User meant:    9:00 AM PT  →  stores 16:00 UTC  →  displays as 9:00 AM PT ✅
        // Mutator converts "09:00" parsed as PT → 16:00 UTC → stored as 16:00 UTC
        // → toISOString() = "2026-05-28T16:00:00.000000Z"
        // → formatDisplayTimeInPT() = "9:00 AM" ✅
    });

    test('validation returns inline errors for missing address fields', function () {
        $start = now()->addDays(1)->setHour(18)->setMinute(0)->setSecond(0);
        $end = (clone $start)->addHours(4);

        $guestData = [
            'client_first_name' => 'John',
            'client_last_name' => 'Guest',
            'client_email' => 'john.guest@example.com',
            'client_phone' => '+11234567890',
            'service_type' => ServiceType::Babysitter->value,
            'location_type' => LocationType::PrivateHome->value,
            'start_datetime' => $start->toDateTimeString(),
            'end_datetime' => $end->toDateTimeString(),
            // address fields intentionally omitted
            'new_children' => [
                ['name' => 'Child 1', 'gender' => 'male', 'birth_month' => '1', 'birth_year' => '2020'],
            ],
        ];

        $response = $this->post(route('guest.bookings.store'), $guestData);

        $response->assertSessionHasErrors([
            'address_line1',
            'address_city',
            'address_state',
            'address_zip',
        ]);
        $response->assertStatus(302);
    });

    test('stores US phone in E.164 format', function () {
        $start = now()->addDays(1)->setHour(18)->setMinute(0)->setSecond(0);
        $end = (clone $start)->addHours(4);

        PricingRule::create([
            'service_type' => ServiceType::Babysitter->value,
            'number_of_children' => 0,
            'is_for_pets' => false,
            'charge_to_client' => 30.00,
            'paid_to_caregiver' => 20.00,
            'sitterwise_cut' => 10.00,
            'payment_form' => 'stripe',
        ]);

        $pendingData = [
            'client_first_name' => 'US',
            'client_last_name' => 'Phone',
            'client_email' => 'us-phone@example.com',
            'client_phone' => '+16195551212',
            'service_type' => ServiceType::Babysitter->value,
            'location_type' => LocationType::PrivateHome->value,
            'start_datetime' => $start->toDateTimeString(),
            'end_datetime' => $end->toDateTimeString(),
            'address_line1' => '555 Phone St',
            'address_city' => 'San Diego',
            'address_state' => 'CA',
            'address_zip' => '92101',
            'new_children' => [
                ['name' => 'Kid', 'gender' => 'male', 'birth_month' => '1', 'birth_year' => '2022'],
            ],
            'sms_consent' => true,
        ];

        $mockService = mock(GuestBookingService::class)->makePartial();
        $mockService->shouldAllowMockingProtectedMethods();
        $mockService->shouldReceive('attachPaymentMethod')->andReturnNull();

        $booking = $mockService->createBookingWithPayment($pendingData, 'pm_test_token');

        $user = User::where('email', 'us-phone@example.com')->first();
        $client = Client::where('user_id', $user->id)->first();

        expect($client->phone)->toBe('+16195551212');
        expect($booking->client_phone)->toBe('+16195551212');
    });

    test('stores hotel_name for unlisted hotel', function () {
        $start = now()->addDays(1)->setHour(18)->setMinute(0)->setSecond(0);
        $end = (clone $start)->addHours(4);

        PricingRule::create([
            'service_type' => ServiceType::Babysitter->value,
            'number_of_children' => 0,
            'is_for_pets' => false,
            'charge_to_client' => 30.00,
            'paid_to_caregiver' => 20.00,
            'sitterwise_cut' => 10.00,
            'payment_form' => 'stripe',
        ]);

        $pendingData = [
            'client_first_name' => 'Unlisted',
            'client_last_name' => 'Hotel',
            'client_email' => 'unlisted.hotel@example.com',
            'client_phone' => '+15551112222',
            'service_type' => ServiceType::Babysitter->value,
            'location_type' => LocationType::Hotel->value,
            'start_datetime' => $start->toDateTimeString(),
            'end_datetime' => $end->toDateTimeString(),
            'address_line1' => '100 Unlisted Blvd',
            'address_city' => 'San Diego',
            'address_state' => 'CA',
            'address_zip' => '92101',
            'hotel_id' => null,
            'hotel_name' => 'My Unlisted Hotel',
            'new_children' => [
                ['name' => 'Child 1', 'gender' => 'male', 'birth_month' => '1', 'birth_year' => '2020'],
            ],
            'sms_consent' => true,
        ];

        $mockService = mock(GuestBookingService::class)->makePartial();
        $mockService->shouldAllowMockingProtectedMethods();
        $mockService->shouldReceive('attachPaymentMethod')->andReturnNull();

        $booking = $mockService->createBookingWithPayment($pendingData, 'pm_test_token');
        $booking->load('bookingGroup');

        expect($booking->bookingGroup->hotel_name)->toBe('My Unlisted Hotel');
        expect($booking->bookingGroup->hotel_id)->toBeNull();

        // Verify the resolution fallback works: bookingGroup->hotel_name should be used
        // since there's no hotel record (hotel_id is null)
        expect($booking->bookingGroup->hotel_name ?? $booking->hotel?->name)->toBe('My Unlisted Hotel');
    });

    test('stores international phone in E.164 format', function () {
        $start = now()->addDays(1)->setHour(18)->setMinute(0)->setSecond(0);
        $end = (clone $start)->addHours(4);

        PricingRule::create([
            'service_type' => ServiceType::Babysitter->value,
            'number_of_children' => 0,
            'is_for_pets' => false,
            'charge_to_client' => 30.00,
            'paid_to_caregiver' => 20.00,
            'sitterwise_cut' => 10.00,
            'payment_form' => 'stripe',
        ]);

        $pendingData = [
            'client_first_name' => 'International',
            'client_last_name' => 'Phone',
            'client_email' => 'intl-phone@example.com',
            'client_phone' => '+447900123456',
            'service_type' => ServiceType::Babysitter->value,
            'location_type' => LocationType::PrivateHome->value,
            'start_datetime' => $start->toDateTimeString(),
            'end_datetime' => $end->toDateTimeString(),
            'address_line1' => '123 London Rd',
            'address_city' => 'San Diego',
            'address_state' => 'CA',
            'address_zip' => '92101',
            'new_children' => [
                ['name' => 'Kid', 'gender' => 'male', 'birth_month' => '3', 'birth_year' => '2023'],
            ],
            'sms_consent' => true,
        ];

        $mockService = mock(GuestBookingService::class)->makePartial();
        $mockService->shouldAllowMockingProtectedMethods();
        $mockService->shouldReceive('attachPaymentMethod')->andReturnNull();

        $booking = $mockService->createBookingWithPayment($pendingData, 'pm_test_token');

        $user = User::where('email', 'intl-phone@example.com')->first();
        $client = Client::where('user_id', $user->id)->first();

        expect($client->phone)->toBe('+447900123456');
        expect($booking->client_phone)->toBe('+447900123456');
    });

    test('validation rejects missing sms_consent', function () {
        $start = now()->addDays(1)->setHour(18)->setMinute(0)->setSecond(0);
        $end = (clone $start)->addHours(4);

        $guestData = [
            'client_first_name' => 'No',
            'client_last_name' => 'Consent',
            'client_email' => 'no.consent@example.com',
            'client_phone' => '+11234567890',
            'service_type' => ServiceType::Babysitter->value,
            'location_type' => LocationType::PrivateHome->value,
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
            // sms_consent intentionally omitted
        ];

        $response = $this->post(route('guest.bookings.store'), $guestData);

        $response->assertSessionHasErrors(['sms_consent']);
        $response->assertStatus(302);
    });

    test('sms consent Yes stores sms_opted_out as false', function () {
        $start = now()->addDays(1)->setHour(18)->setMinute(0)->setSecond(0);
        $end = (clone $start)->addHours(4);

        PricingRule::create([
            'service_type' => ServiceType::Babysitter->value,
            'number_of_children' => 0,
            'is_for_pets' => false,
            'charge_to_client' => 30.00,
            'paid_to_caregiver' => 20.00,
            'sitterwise_cut' => 10.00,
            'payment_form' => 'stripe',
        ]);

        $pendingData = [
            'client_first_name' => 'Consent',
            'client_last_name' => 'Yes',
            'client_email' => 'consent.yes@example.com',
            'client_phone' => '+15551234567',
            'service_type' => ServiceType::Babysitter->value,
            'location_type' => LocationType::PrivateHome->value,
            'start_datetime' => $start->toDateTimeString(),
            'end_datetime' => $end->toDateTimeString(),
            'address_line1' => '123 Yes St',
            'address_city' => 'San Diego',
            'address_state' => 'CA',
            'address_zip' => '92101',
            'new_children' => [
                ['name' => 'Kid', 'gender' => 'male', 'birth_month' => '1', 'birth_year' => '2022'],
            ],
            'sms_consent' => true,
        ];

        $mockService = mock(GuestBookingService::class)->makePartial();
        $mockService->shouldAllowMockingProtectedMethods();
        $mockService->shouldReceive('attachPaymentMethod')->andReturnNull();

        $mockService->createBookingWithPayment($pendingData, 'pm_test_token');

        $user = User::where('email', 'consent.yes@example.com')->first();
        $client = Client::where('user_id', $user->id)->first();

        expect($client->sms_opted_out)->toBeFalse();
    });

    test('sms consent No stores sms_opted_out as true', function () {
        $start = now()->addDays(1)->setHour(18)->setMinute(0)->setSecond(0);
        $end = (clone $start)->addHours(4);

        PricingRule::create([
            'service_type' => ServiceType::Babysitter->value,
            'number_of_children' => 0,
            'is_for_pets' => false,
            'charge_to_client' => 30.00,
            'paid_to_caregiver' => 20.00,
            'sitterwise_cut' => 10.00,
            'payment_form' => 'stripe',
        ]);

        $pendingData = [
            'client_first_name' => 'Consent',
            'client_last_name' => 'No',
            'client_email' => 'consent.no@example.com',
            'client_phone' => '+15559876543',
            'service_type' => ServiceType::Babysitter->value,
            'location_type' => LocationType::PrivateHome->value,
            'start_datetime' => $start->toDateTimeString(),
            'end_datetime' => $end->toDateTimeString(),
            'address_line1' => '456 No Way',
            'address_city' => 'San Diego',
            'address_state' => 'CA',
            'address_zip' => '92101',
            'new_children' => [
                ['name' => 'Kid', 'gender' => 'male', 'birth_month' => '3', 'birth_year' => '2023'],
            ],
            'sms_consent' => false,
        ];

        $mockService = mock(GuestBookingService::class)->makePartial();
        $mockService->shouldAllowMockingProtectedMethods();
        $mockService->shouldReceive('attachPaymentMethod')->andReturnNull();

        $mockService->createBookingWithPayment($pendingData, 'pm_test_token');

        $user = User::where('email', 'consent.no@example.com')->first();
        $client = Client::where('user_id', $user->id)->first();

        expect($client->sms_opted_out)->toBeTrue();
    });
});
