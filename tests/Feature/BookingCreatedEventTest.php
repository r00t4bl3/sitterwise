<?php

use App\Events\BookingCreated;
use App\Events\BookingGroupCreated;
use App\Models\Booking;
use App\Models\Client;
use App\Models\ClientAddress;
use App\Models\ClientChild;
use App\Models\Hotel;
use App\Models\PricingRule;
use App\Models\User;
use App\Services\Booking\GuestBookingService;
use Database\Seeders\PricingRulesTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PricingRulesTableSeeder::class);
});

describe('BookingCreated fires for single-date submissions', function () {
    test('guest single-date fires BookingCreated only', function () {
        $start = now()->addDays(1)->setHour(18)->setMinute(0)->setSecond(0);
        $end = (clone $start)->addHours(4);

        $pendingData = [
            'client_first_name' => 'Test',
            'client_last_name' => 'Guest',
            'client_email' => 'test.guest@example.com',
            'client_phone' => '+11234567890',
            'service_type' => 'babysitter',
            'location_type' => 'private_home',
            'start_datetime' => $start->toDateTimeString(),
            'end_datetime' => $end->toDateTimeString(),
            'address_line1' => '123 Test St',
            'address_city' => 'San Diego',
            'address_state' => 'CA',
            'address_zip' => '92101',
            'new_children' => [
                ['name' => 'Kid', 'gender' => 'male', 'birth_month' => '1', 'birth_year' => '2020'],
            ],
        ];

        Event::fake([BookingCreated::class, BookingGroupCreated::class]);

        $mockService = mock(GuestBookingService::class)->makePartial();
        $mockService->shouldAllowMockingProtectedMethods();
        $mockService->shouldReceive('attachPaymentMethod')->andReturnNull();

        $mockService->createBookingWithPayment($pendingData, 'pm_test_token');

        Event::assertDispatched(BookingCreated::class);
        Event::assertNotDispatched(BookingGroupCreated::class);
    });

    test('admin single-date fires BookingCreated only', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = Client::factory()->create();
        $hotel = Hotel::factory()->create();

        Event::fake([BookingCreated::class, BookingGroupCreated::class]);

        $response = $this->actingAs($admin)->post(route('bookings.store'), [
            'client_id' => $client->id,
            'service_type' => 'babysitter',
            'location_type' => 'hotel',
            'start_datetime' => now()->addDays(1)->setHour(14)->toISOString(),
            'end_datetime' => now()->addDays(1)->setHour(18)->toISOString(),
            'hotel_id' => $hotel->id,
            'total_amount' => 100,
            'status' => 'received',
            'payment_status' => 'pending',
            'address_line1' => '123 Hotel Way',
            'address_city' => 'Los Angeles',
            'address_state' => 'CA',
            'address_zip' => '90001',
            'child_ids' => [],
            'new_children' => [
                ['name' => 'Kid', 'gender' => 'male', 'birth_month' => '1', 'birth_year' => '2020'],
            ],
        ]);

        $response->assertRedirect();
        Event::assertDispatched(BookingCreated::class);
        Event::assertNotDispatched(BookingGroupCreated::class);
    });

    test('client single-date fires BookingCreated only', function () {
        $user = User::factory()->create(['role' => 'client']);
        $client = Client::factory()->for($user)->create();
        $child = ClientChild::factory()->for($client)->create();

        Event::fake([BookingCreated::class, BookingGroupCreated::class]);

        $start = now()->addDays(2)->setHour(9)->setMinute(0);
        $end = (clone $start)->addHours(8);

        $response = $this->actingAs($user)->post(route('bookings.store'), [
            'service_type' => 'babysitter',
            'location_type' => 'private_home',
            'start_datetime' => $start->toISOString(),
            'end_datetime' => $end->toISOString(),
            'address_line1' => '123 Home St',
            'address_city' => 'San Diego',
            'address_state' => 'CA',
            'address_zip' => '92101',
            'child_ids' => [$child->id],
            'new_children' => [],
            'new_pets' => [],
            'sitter_preferences' => [],
            'children_notes' => '',
            'status' => 'received',
            'payment_status' => 'pending',
        ]);

        $response->assertRedirect();
        Event::assertDispatched(BookingCreated::class);
        Event::assertNotDispatched(BookingGroupCreated::class);
    });
});

describe('BookingGroupCreated fires for multi-date submissions', function () {
    test('guest multi-date fires BookingGroupCreated only', function () {
        $start1 = now()->addDays(1)->setHour(18)->setMinute(0)->setSecond(0);
        $end1 = (clone $start1)->addHours(4);
        $start2 = now()->addDays(2)->setHour(18)->setMinute(0)->setSecond(0);
        $end2 = (clone $start2)->addHours(4);

        $pendingData = [
            'client_first_name' => 'Multi',
            'client_last_name' => 'Guest',
            'client_email' => 'multi.guest@example.com',
            'client_phone' => '+11234567890',
            'service_type' => 'babysitter',
            'location_type' => 'private_home',
            'start_datetime' => $start1->toDateTimeString(),
            'end_datetime' => $end1->toDateTimeString(),
            'address_line1' => '123 Test St',
            'address_city' => 'San Diego',
            'address_state' => 'CA',
            'address_zip' => '92101',
            'dates' => [
                ['start_datetime' => $start1->toDateTimeString(), 'end_datetime' => $end1->toDateTimeString()],
                ['start_datetime' => $start2->toDateTimeString(), 'end_datetime' => $end2->toDateTimeString()],
            ],
            'new_children' => [
                ['name' => 'Kid', 'gender' => 'male', 'birth_month' => '1', 'birth_year' => '2020'],
            ],
        ];

        Event::fake([BookingCreated::class, BookingGroupCreated::class]);

        $mockService = mock(GuestBookingService::class)->makePartial();
        $mockService->shouldAllowMockingProtectedMethods();
        $mockService->shouldReceive('attachPaymentMethod')->andReturnNull();

        $mockService->createBookingWithPayment($pendingData, 'pm_test_token');

        Event::assertDispatched(BookingGroupCreated::class);
        Event::assertNotDispatched(BookingCreated::class);
    });

    test('admin multi-date fires BookingGroupCreated only', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = Client::factory()->create();
        $hotel = Hotel::factory()->create();

        Event::fake([BookingCreated::class, BookingGroupCreated::class]);

        $start1 = now()->addDays(1)->setHour(14)->setMinute(0)->setSecond(0);
        $end1 = (clone $start1)->addHours(4);
        $start2 = now()->addDays(2)->setHour(14)->setMinute(0)->setSecond(0);
        $end2 = (clone $start2)->addHours(4);

        $response = $this->actingAs($admin)->post(route('bookings.store'), [
            'client_id' => $client->id,
            'service_type' => 'babysitter',
            'location_type' => 'hotel',
            'start_datetime' => $start1->toISOString(),
            'end_datetime' => $end1->toISOString(),
            'hotel_id' => $hotel->id,
            'total_amount' => 100,
            'status' => 'received',
            'payment_status' => 'pending',
            'address_line1' => '123 Hotel Way',
            'address_city' => 'Los Angeles',
            'address_state' => 'CA',
            'address_zip' => '90001',
            'child_ids' => [],
            'new_children' => [
                ['name' => 'Kid', 'gender' => 'male', 'birth_month' => '1', 'birth_year' => '2020'],
            ],
            'dates' => [
                ['start_datetime' => $start1->toISOString(), 'end_datetime' => $end1->toISOString()],
                ['start_datetime' => $start2->toISOString(), 'end_datetime' => $end2->toISOString()],
            ],
        ]);

        $response->assertRedirect();
        Event::assertDispatched(BookingGroupCreated::class);
        Event::assertNotDispatched(BookingCreated::class);
    });

    test('client multi-date fires BookingGroupCreated only', function () {
        $user = User::factory()->create(['role' => 'client']);
        $client = Client::factory()->for($user)->create();
        $child = ClientChild::factory()->for($client)->create();

        Event::fake([BookingCreated::class, BookingGroupCreated::class]);

        $start1 = now()->addDays(2)->setHour(9)->setMinute(0);
        $end1 = (clone $start1)->addHours(8);
        $start2 = now()->addDays(3)->setHour(9)->setMinute(0);
        $end2 = (clone $start2)->addHours(8);

        $response = $this->actingAs($user)->post(route('bookings.store'), [
            'service_type' => 'babysitter',
            'location_type' => 'private_home',
            'start_datetime' => $start1->toISOString(),
            'end_datetime' => $end1->toISOString(),
            'address_line1' => '123 Home St',
            'address_city' => 'San Diego',
            'address_state' => 'CA',
            'address_zip' => '92101',
            'child_ids' => [$child->id],
            'new_children' => [],
            'new_pets' => [],
            'sitter_preferences' => [],
            'children_notes' => '',
            'status' => 'received',
            'payment_status' => 'pending',
            'dates' => [
                ['start_datetime' => $start1->toISOString(), 'end_datetime' => $end1->toISOString()],
                ['start_datetime' => $start2->toISOString(), 'end_datetime' => $end2->toISOString()],
            ],
        ]);

        $response->assertRedirect();
        Event::assertDispatched(BookingGroupCreated::class);
        Event::assertNotDispatched(BookingCreated::class);
    });
});
