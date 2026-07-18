<?php

use App\Enums\BookingPaymentStatus;
use App\Enums\BookingStatus;
use App\Enums\ServiceType;
use App\Models\Booking;
use App\Models\BookingGroup;
use App\Models\Client;
use App\Models\User;
use App\Notifications\BookingCreatedNotification;
use App\Notifications\ClientGroupBookingCreatedNotification;
use App\Services\ClientPayment\ClientPaymentService;
use Database\Seeders\AttributeDefinitionSeeder;
use Database\Seeders\CertificationTypeSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\PricingRulesTableSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\SpecialtyTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $this->seed([
        SettingsSeeder::class,
        PricingRulesTableSeeder::class,
        CertificationTypeSeeder::class,
        SpecialtyTypeSeeder::class,
        LocationSeeder::class,
        AttributeDefinitionSeeder::class,
    ]);
    $this->user = User::factory()->create(['role' => 'client']);
    $this->client = Client::factory()->for($this->user)->create();
});

// A Stripe, requires-payment group so the deferred-send query matches.
function deferredStripeGroup(int $clientId): BookingGroup
{
    return BookingGroup::factory()->create([
        'client_id' => $clientId,
        'service_type' => ServiceType::Babysitter->value,
        'requires_payment' => true,
    ]);
}

// Invoke the exact code that runs when a client adds a payment method.
function runDeferredSend(Client $client): void
{
    $service = (new ReflectionClass(ClientPaymentService::class))->newInstanceWithoutConstructor();
    $method = new ReflectionMethod(ClientPaymentService::class, 'sendDeferredBookingNotifications');
    $method->setAccessible(true);
    $method->invoke($service, $client);
}

test('deferred send still emails the client for a booking still awaiting a caregiver', function () {
    $received = Booking::factory()->create([
        'booking_group_id' => deferredStripeGroup($this->client->id)->id,
        'status' => BookingStatus::Received->value,
        'payment_status' => BookingPaymentStatus::Pending->value,
    ]);

    runDeferredSend($this->client);

    Notification::assertSentTo(
        $this->user,
        BookingCreatedNotification::class,
        fn ($n) => $n->booking->id === $received->id,
    );
});

test('deferred send does NOT email the client for a booking a caregiver already accepted', function () {
    $confirmed = Booking::factory()->create([
        'booking_group_id' => deferredStripeGroup($this->client->id)->id,
        'status' => BookingStatus::Confirmed->value,
        'payment_status' => BookingPaymentStatus::Pending->value,
    ]);

    runDeferredSend($this->client);

    Notification::assertNotSentTo(
        $this->user,
        BookingCreatedNotification::class,
        fn ($n) => $n->booking->id === $confirmed->id,
    );
});

test('deferred group send is skipped when every booking in the group is already accepted', function () {
    $group = deferredStripeGroup($this->client->id);
    Booking::factory()->count(2)->create([
        'booking_group_id' => $group->id,
        'status' => BookingStatus::Confirmed->value,
        'payment_status' => BookingPaymentStatus::Pending->value,
    ]);

    runDeferredSend($this->client);

    Notification::assertNotSentTo($this->user, ClientGroupBookingCreatedNotification::class);
});

test('deferred group send still fires when the group has an un-accepted booking', function () {
    $group = deferredStripeGroup($this->client->id);
    Booking::factory()->create([
        'booking_group_id' => $group->id,
        'status' => BookingStatus::Received->value,
        'payment_status' => BookingPaymentStatus::Pending->value,
    ]);
    Booking::factory()->create([
        'booking_group_id' => $group->id,
        'status' => BookingStatus::Confirmed->value,
        'payment_status' => BookingPaymentStatus::Pending->value,
    ]);

    runDeferredSend($this->client);

    Notification::assertSentTo($this->user, ClientGroupBookingCreatedNotification::class);
});
