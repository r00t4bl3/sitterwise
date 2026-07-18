<?php

use App\Enums\BookingStatus;
use App\Jobs\NotifyCaregiversJob;
use App\Models\Booking;
use App\Models\BookingGroup;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\User;
use App\Notifications\BookingInvitationNotification;
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

    $clientUser = User::factory()->create(['role' => 'client']);
    $this->client = Client::factory()->for($clientUser)->create();
});

/**
 * Regression for #92: NotifyCaregiversJob must re-check the booking's CURRENT
 * status. The admin's guard runs at DISPATCH time; the queued job (and its
 * retries) can run later — after the booking was accepted or cancelled.
 */
test('does NOT invite caregivers when the booking was accepted before the job ran', function () {
    $group = BookingGroup::factory()->create(['client_id' => $this->client->id]);

    // Booking is open when the admin fires the notify action.
    $booking = Booking::factory()->create([
        'booking_group_id' => $group->id,
        'caregiver_id' => null,
        'status' => BookingStatus::Received->value,
    ]);

    $invited = Caregiver::factory()->create();

    // Job is enqueued while the booking is still Received (serialized snapshot).
    $job = new NotifyCaregiversJob($booking, [$invited->id]);

    // ...then, before the worker runs it, the booking gets accepted.
    $booking->forceFill(['status' => BookingStatus::Confirmed->value])->saveQuietly();

    $job->handle();

    Notification::assertNotSentTo($invited->user, BookingInvitationNotification::class);
});

test('does NOT invite caregivers when the booking was cancelled before the job ran', function () {
    $group = BookingGroup::factory()->create(['client_id' => $this->client->id]);

    $booking = Booking::factory()->create([
        'booking_group_id' => $group->id,
        'caregiver_id' => null,
        'status' => BookingStatus::Received->value,
    ]);

    $invited = Caregiver::factory()->create();

    $job = new NotifyCaregiversJob($booking, [$invited->id]);

    $booking->forceFill(['status' => BookingStatus::Cancelled->value])->saveQuietly();

    $job->handle();

    Notification::assertNotSentTo($invited->user, BookingInvitationNotification::class);
});

test('still invites caregivers for a booking that is genuinely open', function () {
    $group = BookingGroup::factory()->create(['client_id' => $this->client->id]);

    $booking = Booking::factory()->create([
        'booking_group_id' => $group->id,
        'caregiver_id' => null,
        'status' => BookingStatus::Received->value,
    ]);

    $invited = Caregiver::factory()->create();

    (new NotifyCaregiversJob($booking, [$invited->id]))->handle();

    Notification::assertSentTo($invited->user, BookingInvitationNotification::class);
});
