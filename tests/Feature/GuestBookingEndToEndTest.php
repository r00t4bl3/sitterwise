<?php

use App\Enums\LocationType;
use App\Enums\ServiceType;
use App\Models\Booking;
use App\Models\Client;
use App\Models\PricingRule;
use App\Models\User;
use App\Services\Booking\GuestBookingService;
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
            'client_phone' => '1234567890',
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
            'client_phone' => '9876543210',
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
});
