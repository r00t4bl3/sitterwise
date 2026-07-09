<?php

use App\Events\BookingReminderTriggered;
use App\Models\Booking;
use App\Models\Caregiver;
use App\Models\Client;
use App\Support\Settings;
use Database\Seeders\AttributeDefinitionSeeder;
use Database\Seeders\CertificationTypeSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\SpecialtyTypeSeeder;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->seed([
        SettingsSeeder::class,
        CertificationTypeSeeder::class,
        SpecialtyTypeSeeder::class,
        LocationSeeder::class,
        AttributeDefinitionSeeder::class,
    ]);
    $this->client = Client::factory()->create();
    $this->caregiver = Caregiver::factory()->create();
});

test('booking reminder lead time is settings-driven', function () {
    Event::fake([BookingReminderTriggered::class]);

    // A confirmed booking ~47.5 hours out — outside the default 24h window.
    Booking::factory()->forClient($this->client)->create([
        'caregiver_id' => $this->caregiver->id,
        'status' => 'confirmed',
        'start_datetime' => now()->addHours(47)->addMinutes(30),
        'end_datetime' => now()->addHours(51),
    ]);

    // Default lead = 24h → window [23,24] → not reminded.
    $this->artisan('app:send-booking-reminders')->assertOk();
    Event::assertNotDispatched(BookingReminderTriggered::class);

    // Push the lead time to 48h → window [47,48] → now it qualifies.
    Settings::set('bookings.reminder_hours_before', 48);
    $this->artisan('app:send-booking-reminders')->assertOk();
    Event::assertDispatched(BookingReminderTriggered::class);
});
