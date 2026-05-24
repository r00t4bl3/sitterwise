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

    test('children and pets are saved to both client_{children,pets} tables and bookings JSON columns', function () {
        $start = now()->addDays(1)->setHour(18)->setMinute(0)->setSecond(0);
        $end = (clone $start)->addHours(4);

        $pendingData = [
            'client_first_name' => 'Sarah',
            'client_last_name' => 'Test',
            'client_email' => 'sarah.test@example.com',
            'client_phone' => '5551234567',
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
});
