<?php

use App\Models\Booking;
use App\Models\BookingCaregiverNotification;
use App\Models\Caregiver;
use App\Models\Client;
use App\Services\LifesaverService;
use App\Support\Settings;
use Database\Seeders\AttributeDefinitionSeeder;
use Database\Seeders\CertificationTypeSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\SpecialtyTypeSeeder;

beforeEach(function () {
    $this->seed([
        SettingsSeeder::class,
        CertificationTypeSeeder::class,
        SpecialtyTypeSeeder::class,
        LocationSeeder::class,
        AttributeDefinitionSeeder::class,
    ]);
    $this->client = Client::factory()->create();
    $this->service = app(LifesaverService::class);
});

function lifesaverBooking(Client $client, array $overrides = []): Booking
{
    return Booking::factory()->forClient($client)->create(array_merge([
        'status' => 'received',
        'caregiver_id' => null,
        // Comfortably outside both windows by default.
        'start_datetime' => now()->addDays(10),
        'end_datetime' => now()->addDays(10)->addHours(4),
    ], $overrides));
}

describe('LifesaverService rules', function () {
    test('a normal, recently-notified future booking is not a Lifesaver', function () {
        $booking = lifesaverBooking($this->client);
        BookingCaregiverNotification::create([
            'booking_id' => $booking->id,
            'caregiver_id' => Caregiver::factory()->create()->id,
            'notified_at' => now()->subHour(),
        ]);

        expect($this->service->isLifesaver($booking))->toBeFalse();
    });

    test('rule 2: short-notice creation makes it a Lifesaver', function () {
        // short_notice_hours = 18; start 5h out → short notice.
        $booking = lifesaverBooking($this->client, [
            'start_datetime' => now()->addHours(5),
            'end_datetime' => now()->addHours(9),
        ]);

        expect($this->service->isLifesaver($booking))->toBeTrue();
    });

    test('rule 1: unclaimed past the threshold makes it a Lifesaver', function () {
        // hours_unclaimed = 10; first notified 11h ago, still unassigned/open.
        $booking = lifesaverBooking($this->client);
        BookingCaregiverNotification::create([
            'booking_id' => $booking->id,
            'caregiver_id' => Caregiver::factory()->create()->id,
            'notified_at' => now()->subHours(11),
        ]);

        expect($this->service->isLifesaver($booking))->toBeTrue();
    });

    test('rule 1 does not apply once the booking is assigned', function () {
        $booking = lifesaverBooking($this->client, [
            'status' => 'confirmed',
            'caregiver_id' => Caregiver::factory()->create()->id,
        ]);
        BookingCaregiverNotification::create([
            'booking_id' => $booking->id,
            'caregiver_id' => $booking->caregiver_id,
            'notified_at' => now()->subHours(11),
        ]);

        expect($this->service->isLifesaver($booking))->toBeFalse();
    });

    test('rule 3: admin override forces the flag on and off', function () {
        $booking = lifesaverBooking($this->client); // otherwise not a Lifesaver

        $booking->lifesaver_override = true;
        expect($this->service->isLifesaver($booking))->toBeTrue();

        // Force OFF even when a rule would otherwise trigger.
        $shortNotice = lifesaverBooking($this->client, [
            'start_datetime' => now()->addHours(2),
            'end_datetime' => now()->addHours(6),
            'lifesaver_override' => false,
        ]);
        expect($this->service->isLifesaver($shortNotice))->toBeFalse();
    });

    test('thresholds come from the editable settings store', function () {
        // Tighten short-notice to 2h: a 5h-out booking is no longer short notice.
        Settings::set('lifesaver.short_notice_hours', 2);
        $booking = lifesaverBooking($this->client, [
            'start_datetime' => now()->addHours(5),
            'end_datetime' => now()->addHours(9),
        ]);

        expect($this->service->isLifesaver($booking))->toBeFalse();
    });
});

describe('wasLifesaverRescue (for badges)', function () {
    test('true when the caregiver confirmed after the unclaimed threshold', function () {
        $caregiver = Caregiver::factory()->create();
        $booking = lifesaverBooking($this->client, [
            'status' => 'completed',
            'caregiver_id' => $caregiver->id,
            'confirmed_at' => now(),
        ]);
        BookingCaregiverNotification::create([
            'booking_id' => $booking->id,
            'caregiver_id' => $caregiver->id,
            'notified_at' => now()->subHours(12), // sat 12h > 10h before confirm
        ]);

        expect($this->service->wasLifesaverRescue($booking))->toBeTrue();
    });

    test('false when confirmed promptly', function () {
        $caregiver = Caregiver::factory()->create();
        $booking = lifesaverBooking($this->client, [
            'status' => 'completed',
            'caregiver_id' => $caregiver->id,
            'confirmed_at' => now(),
        ]);
        BookingCaregiverNotification::create([
            'booking_id' => $booking->id,
            'caregiver_id' => $caregiver->id,
            'notified_at' => now()->subHours(2),
        ]);

        expect($this->service->wasLifesaverRescue($booking))->toBeFalse();
    });
});
