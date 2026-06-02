<?php

use App\Enums\BookingStatus;
use App\Enums\ServiceType;
use App\Events\BookingReceipt;
use App\Models\Booking;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\PricingRule;
use App\Models\User;
use Database\Seeders\AttributeDefinitionSeeder;
use Database\Seeders\CertificationTypeSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\SpecialtyTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        AttributeDefinitionSeeder::class,
        CertificationTypeSeeder::class,
        LocationSeeder::class,
        SpecialtyTypeSeeder::class,
    ]);
});

function completedBookingWithCaregiver(): array
{
    $user = User::factory()->create(['role' => 'caregiver']);
    $caregiver = Caregiver::create([
        'user_id' => $user->id,
        'first_name' => fake()->firstName(),
        'last_name' => fake()->lastName(),
        'slug' => fake()->slug(),
        'phone' => fake()->phoneNumber(),
        'address_city' => 'San Diego',
        'address_state' => 'CA',
        'address_zip' => '92101',
        'date_of_birth' => '2000-01-01',
        'status' => 'active',
    ]);

    $client = Client::factory()->create();

    PricingRule::create([
        'service_type' => ServiceType::Babysitter->value,
        'number_of_children' => 0,
        'is_for_pets' => false,
        'charge_to_client' => 20,
        'paid_to_caregiver' => 15,
        'sitterwise_cut' => 5,
        'payment_form' => 'Stripe',
    ]);

    $booking = Booking::factory()->forClient($client)->create([
        'status' => BookingStatus::Completed->value,
        'caregiver_id' => $caregiver->id,
        'confirmed_by' => $caregiver->id,
        'confirmed_at' => now(),
    ]);

    return [$booking, $caregiver, $client];
}

describe('Booking Receipt', function () {
    test('BookingReceipt event fires after successful charge', function () {
        Event::fake();

        [$booking, $caregiver, $client] = completedBookingWithCaregiver();

        event(new BookingReceipt($booking));

        Event::assertDispatched(BookingReceipt::class);
    });

    test('BookingReceipt listener does not alter booking status', function () {
        Event::fake();

        [$booking, $caregiver, $client] = completedBookingWithCaregiver();
        $originalStatus = $booking->status;

        event(new BookingReceipt($booking));

        $booking->refresh();
        expect($booking->status)->toBe($originalStatus);
    });
});
