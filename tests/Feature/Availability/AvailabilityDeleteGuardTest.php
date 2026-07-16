<?php

use App\Enums\BookingPaymentStatus;
use App\Enums\BookingStatus;
use App\Enums\CaregiverStatus;
use App\Enums\ServiceType;
use App\Models\Availability;
use App\Models\Booking;
use App\Models\BookingGroup;
use App\Models\Caregiver;
use App\Models\User;
use App\Services\CaregiverRecommendation\AvailabilityReservationService;
use Database\Seeders\AttributeDefinitionSeeder;
use Database\Seeders\CertificationTypeSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\SpecialtyTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    Mail::fake();
    $this->seed([
        CertificationTypeSeeder::class,
        SpecialtyTypeSeeder::class,
        LocationSeeder::class,
        AttributeDefinitionSeeder::class,
    ]);
});

function guardCaregiverWithAvailability(): Caregiver
{
    $caregiver = Caregiver::factory()->create([
        'status' => CaregiverStatus::Active->value,
    ]);

    Availability::factory()->create([
        'caregiver_id' => $caregiver->id,
        'date' => now()->addDays(5)->format('Y-m-d'),
        'time_slots' => ['morning', 'afternoon', 'evening'],
    ]);

    return $caregiver;
}

function guardReserveDay(Caregiver $caregiver): void
{
    $group = BookingGroup::factory()->create([
        'service_type' => ServiceType::Babysitter->value,
    ]);

    $booking = Booking::factory()->create([
        'booking_group_id' => $group->id,
        'caregiver_id' => $caregiver->id,
        'start_datetime' => now()->addDays(5)->setTime(8, 0, 0),
        'end_datetime' => now()->addDays(5)->setTime(14, 0, 0),
        'status' => BookingStatus::Received->value,
        'payment_status' => BookingPaymentStatus::Pending->value,
        'charge_to_client_hourly' => 25.00,
        'paid_to_caregiver' => 0,
    ]);

    app(AvailabilityReservationService::class)->reserve($booking);
}

test('caregiver clearing a reserved day keeps it but still clears an un-reserved day', function () {
    $caregiver = guardCaregiverWithAvailability(); // availability on now()->addDays(5)
    $reservedDate = now()->addDays(5)->format('Y-m-d');

    $freeDate = now()->addDays(6)->format('Y-m-d');
    Availability::factory()->create([
        'caregiver_id' => $caregiver->id,
        'date' => $freeDate,
        'time_slots' => ['morning'],
    ]);

    guardReserveDay($caregiver);

    $reserved = $caregiver->availabilities()->whereDate('date', $reservedDate)->first();
    expect($reserved->usedSlots()->exists())->toBeTrue();

    $response = $this->actingAs($caregiver->user)->post(
        route('availabilities.store', $caregiver->id),
        ['days' => [
            ['date' => $reservedDate, 'time_slots' => []],
            ['date' => $freeDate, 'time_slots' => []],
        ]],
    );

    $response->assertRedirect();
    $response->assertSessionHas('success', fn ($msg) => str_contains($msg, 'kept'));

    // Reserved day preserved (no FK violation); un-reserved day removed.
    expect(Availability::whereKey($reserved->id)->exists())->toBeTrue();
    expect($caregiver->availabilities()->whereDate('date', $freeDate)->exists())->toBeFalse();
});

test('caregiver destroy blocks a reserved availability but allows an un-reserved one', function () {
    $caregiver = guardCaregiverWithAvailability();
    guardReserveDay($caregiver);

    $reserved = $caregiver->availabilities()->first();
    expect($reserved->usedSlots()->exists())->toBeTrue();

    $this->actingAs($caregiver->user)
        ->delete(route('availabilities.destroy', $reserved->id))
        ->assertSessionHas('error');
    expect(Availability::whereKey($reserved->id)->exists())->toBeTrue();

    $free = Availability::factory()->create([
        'caregiver_id' => $caregiver->id,
        'date' => now()->addDays(9)->format('Y-m-d'),
        'time_slots' => ['morning'],
    ]);
    $this->actingAs($caregiver->user)
        ->delete(route('availabilities.destroy', $free->id))
        ->assertSessionHas('success');
    expect(Availability::whereKey($free->id)->exists())->toBeFalse();
});

test('admin clearing a reserved day keeps it', function () {
    $caregiver = guardCaregiverWithAvailability();
    guardReserveDay($caregiver);
    $reservedDate = now()->addDays(5)->format('Y-m-d');

    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->post(
        route('availabilities.store', $caregiver->id),
        ['days' => [['date' => $reservedDate, 'time_slots' => []]]],
    );

    $response->assertRedirect();
    expect($caregiver->availabilities()->whereDate('date', $reservedDate)->exists())->toBeTrue();
});
