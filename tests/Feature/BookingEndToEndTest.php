<?php

use App\Enums\BookingStatus;
use App\Enums\LocationType;
use App\Enums\ServiceType;
use App\Models\Booking;
use App\Models\BookingRating;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\ClientChild;
use App\Models\User;
use App\Services\Billing\JobBillingService;
use Database\Seeders\AttributeDefinitionSeeder;
use Database\Seeders\CertificationTypeSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\PricingRulesTableSeeder;
use Database\Seeders\SpecialtyTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AttributeDefinitionSeeder::class);
    $this->seed(SpecialtyTypeSeeder::class);
    $this->seed(LocationSeeder::class);
    $this->seed(CertificationTypeSeeder::class);
    $this->seed(PricingRulesTableSeeder::class);
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->client = Client::factory()->create(['stripe_customer_id' => 'cus_test']);
    $this->caregiver = Caregiver::factory()->create();
});

describe('Booking Workflow', function () {
    test('complete lifecycle from creation to rating', function () {

        $admin = $this->admin;
        $client = $this->client;
        $clientUser = $this->client->user;
        $caregiver = $this->caregiver;
        $caregiverUser = $this->caregiver->user;

        // Mock Billing Service to avoid external API calls
        mock(JobBillingService::class)
            ->shouldReceive('charge')
            ->andReturnUsing(function ($booking) {
                $booking->update([
                    'status' => BookingStatus::Paid->value,
                    'payment_status' => 'charged',
                ]);

                return [
                    'success' => true,
                    'message' => 'Payment successful',
                    'payment_intent_id' => 'pi_test',
                    'amount' => 130.00,
                ];
            });

        // 1. Client Creates Booking
        $start = now()->addDays(1)->setHour(18)->setMinute(0)->setSecond(0);
        $end = (clone $start)->addHours(4);

        $child = ClientChild::factory()->create(['client_id' => $client->id]);

        $bookingData = [
            'service_type' => ServiceType::Babysitter->value,
            'location_type' => LocationType::PrivateHome->value,
            'start_datetime' => $start->toDateTimeString(),
            'end_datetime' => $end->toDateTimeString(),
            'address_line1' => '123 Test St',
            'address_city' => 'San Diego',
            'address_state' => 'CA',
            'address_zip' => '92101',
            'child_ids' => [$child->id],
        ];

        actingAs($clientUser)
            ->post(route('bookings.store'), $bookingData)
            ->assertRedirect('/bookings');

        $booking = Booking::firstOrFail();
        expect($booking->status)->toBe(BookingStatus::Received->value);
        expect($booking->client_id)->toBe($client->id);

        // 2. Admin Notifies Caregiver
        actingAs($admin)
            ->post(route('bookings.notify', $booking->id), [
                'caregiver_ids' => [$caregiver->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('booking_caregiver_notifications', [
            'booking_id' => $booking->id,
            'caregiver_id' => $caregiver->id,
        ]);

        // 3. Caregiver Reserves Booking
        actingAs($caregiverUser)
            ->post(route('bookings.reserve', $booking->id))
            ->assertRedirect();

        $booking->refresh();
        expect($booking->status)->toBe('reserved');
        expect($booking->reserved_by)->toBe($caregiver->id);

        // 4. Caregiver Confirms Booking
        actingAs($caregiverUser)
            ->post(route('bookings.confirm', $booking->id))
            ->assertRedirect(route('jobs.index'));

        $booking->refresh();
        expect($booking->status)->toBe(BookingStatus::Confirmed->value);
        expect($booking->caregiver_id)->toBe($caregiver->id);

        // 5. Caregiver Completes Job (Checkout)
        actingAs($caregiverUser)
            ->post(route('jobs.checkout', $booking->id), [
                'start_datetime' => $booking->start_datetime->format('Y-m-d H:i:s'),
                'end_datetime' => $booking->end_datetime->format('Y-m-d H:i:s'),
                'reimbursement' => 10.00,
                'reimbursement_description' => 'Parking',
                'bonus' => 0,
            ])
            ->assertRedirect();

        $booking->refresh();
        expect($booking->status)->toBe(BookingStatus::Completed->value);
        expect((float) $booking->reimbursement)->toBe(10.00);

        // 6. Admin Processes Payment
        actingAs($admin)
            ->post(route('bookings.processPayment', $booking->id), [
                'total_working_hour' => $booking->total_working_hour,
                'reimbursement' => $booking->reimbursement,
                'reimbursement_description' => $booking->reimbursement_description,
                'tip' => 0,
                'bonus' => 0,
            ])
            ->assertRedirect();

        $booking->refresh();
        expect($booking->status)->toBe(BookingStatus::Paid->value);
        expect($booking->payment_status)->toBe('charged');

        // 7. Client Rates Caregiver
        actingAs($clientUser)
            ->post(route('jobs.rate', $booking->id), [
                'type' => BookingRating::TYPE_CLIENT_TO_CAREGIVER,
                'rating' => 5,
                'comment' => 'Great sitter!',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('booking_ratings', [
            'booking_id' => $booking->id,
            'rater_id' => $clientUser->id,
            'ratable_id' => $caregiver->id,
            'rating' => 5,
        ]);
    });

    test('admin can create a booking for a new client and assign a caregiver immediately', function () {

        $admin = $this->admin;
        $caregiverUser = $this->caregiver->user;
        $caregiver = $this->caregiver;

        // 1. Admin Creates Booking for a New Client
        $start = now()->addDays(2)->setHour(10)->setMinute(0)->setSecond(0);
        $end = (clone $start)->addHours(5);

        $adminData = [
            'service_type' => ServiceType::Babysitter->value,
            'location_type' => LocationType::PrivateHome->value,
            'start_datetime' => $start->toDateTimeString(),
            'end_datetime' => $end->toDateTimeString(),
            'address_line1' => '789 Admin St',
            'address_city' => 'San Diego',
            'address_state' => 'CA',
            'address_zip' => '92101',
            'status' => BookingStatus::Confirmed->value, // Admin can set directly to confirmed
            'payment_status' => 'pending',
            'caregiver_id' => $caregiver->id, // Admin can assign immediately
            'new_client' => [
                'first_name' => 'New',
                'last_name' => 'Client',
                'email' => 'new.client@example.com',
                'phone' => '+15551234567',
                'client_type' => 'vacationer',
            ],
            'new_children' => [
                ['name' => 'Admin Kid', 'gender' => 'female', 'birth_year' => '2022'],
            ],
        ];

        actingAs($admin)
            ->post(route('bookings.store'), $adminData)
            ->assertRedirect();

        // 2. Verify Client was created
        $newClientUser = User::where('email', 'new.client@example.com')->first();
        expect($newClientUser)->not->toBeNull();
        expect($newClientUser->role)->toBe('client');

        $newClient = Client::where('user_id', $newClientUser->id)->first();
        expect($newClient)->not->toBeNull();

        // 3. Verify Booking was created correctly
        $booking = Booking::where('client_id', $newClient->id)->first();
        expect($booking->status)->toBe(BookingStatus::Confirmed->value);
        expect($booking->caregiver_id)->toBe($caregiver->id);
        expect($booking->address_line1)->toBe('789 Admin St');

        // 4. Verify children snapshot
        expect($booking->children)->toHaveCount(1);
        expect($booking->children[0]['name'])->toBe('Admin Kid');
    });
});
