<?php

use App\Models\AttributeDefinition;
use App\Models\Booking;
use App\Models\BookingCaregiverNotification;
use App\Models\CertificationType;
use App\Models\Client;
use App\Models\Location;
use App\Models\SpecialtyType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('caregiver can reserve and confirm a booking', function () {
    SpecialtyType::factory()->count(5)->create(['is_active' => true]);
    Location::factory()->count(5)->create(['is_active' => true]);
    AttributeDefinition::factory()->count(5)->create(['is_active' => true, 'entity_type' => 'caregiver']);
    CertificationType::factory()->count(5)->create(['is_active' => true]);

    $clientUser = User::factory()->create(['role' => 'client']);
    $client = Client::factory()->create(['user_id' => $clientUser->id]);
    $booking = Booking::factory()->forClient($client)->create();

    $caregiverUser = createCaregiver();

    BookingCaregiverNotification::create([
        'booking_id' => $booking->id,
        'caregiver_id' => $caregiverUser->caregiver->id,
        'notified_at' => now(),
        'claimed' => false,
    ]);

    $this->actingAs($caregiverUser);

    $page = visit("/bookings/{$booking->ulid}");

    $page->assertSee('Accept Booking');

    $page->script(<<<'JS'
        const buttons = Array.from(document.querySelectorAll('button'));
        const acceptBtn = buttons.find(b => b.textContent.includes('Accept Booking'));
        if (acceptBtn) acceptBtn.click();
    JS);

    $page->waitForText('Confirm Booking', 5);

    $page->script(<<<'JS'
        const buttons = Array.from(document.querySelectorAll('button'));
        const confirmBtn = buttons.find(b => b.textContent.includes('Confirm Booking'));
        if (confirmBtn) confirmBtn.click();
    JS);

    $page->waitForText('Booking confirmed', 10)
        ->assertNoJavaScriptErrors();
});
