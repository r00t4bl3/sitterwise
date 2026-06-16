<?php

use App\Enums\CaregiverStatus;
use App\Models\Availability;
use App\Models\Caregiver;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function createCaregiver(): Caregiver
{
    $user = User::factory()->create(['role' => 'caregiver']);

    return Caregiver::query()->create([
        'user_id' => $user->id,
        'first_name' => 'Test',
        'last_name' => 'Caregiver',
        'slug' => 'test-caregiver-'.Str::random(5),
        'phone' => '123-456-7890',
        'address_city' => 'San Diego',
        'address_state' => 'CA',
        'address_zip' => '92000',
        'date_of_birth' => '1990-01-01',
        'status' => CaregiverStatus::Active->value,
    ]);
}

test('can be instantiated', function () {
    $caregiver = Caregiver::factory()->make(['status' => CaregiverStatus::Active->value]);
    $availability = Availability::factory()->make(['caregiver_id' => $caregiver->id]);

    $this->assertInstanceOf(Availability::class, $availability);
});

test('has correct fillable fields', function () {
    $caregiver = Caregiver::factory()->make(['status' => CaregiverStatus::Active->value]);
    $availability = Availability::factory()->make(['caregiver_id' => $caregiver->id]);

    $this->assertNotNull($availability->date);
    $this->assertIsArray($availability->time_slots);
});

test('casts date as date', function () {
    $caregiver = Caregiver::factory()->make(['status' => CaregiverStatus::Active->value]);
    $availability = Availability::factory()->make([
        'caregiver_id' => $caregiver->id,
        'date' => '2026-12-25',
        'time_slots' => ['morning'],
    ]);

    $this->assertInstanceOf(CarbonImmutable::class, $availability->date);
    $this->assertEquals('2026-12-25', $availability->date->toDateString());
});

test('casts time slots as array', function () {
    $caregiver = Caregiver::factory()->make(['status' => CaregiverStatus::Active->value]);
    $availability = Availability::factory()->make([
        'caregiver_id' => $caregiver->id,
        'time_slots' => ['morning', 'afternoon'],
    ]);

    $this->assertIsArray($availability->time_slots);
    $this->assertContains('morning', $availability->time_slots);
    $this->assertContains('afternoon', $availability->time_slots);
});

test('defines caregiver relationship', function () {
    $caregiver = Caregiver::factory()->make(['status' => CaregiverStatus::Active->value]);
    $availability = Availability::factory()->make(['caregiver_id' => $caregiver->id]);

    $relation = $availability->caregiver();

    $this->assertInstanceOf(BelongsTo::class, $relation);
    $this->assertInstanceOf(Caregiver::class, $relation->getRelated());
});

test('in the future scope includes PT-today during UTC night', function () {
    $caregiver = createCaregiver();

    // Freeze time at 2026-06-17 03:00 UTC = 2026-06-16 20:00 PT (PDT, UTC-7)
    $this->travelTo(CarbonImmutable::parse('2026-06-17 03:00:00', 'UTC'));

    $availability = Availability::factory()->create([
        'caregiver_id' => $caregiver->id,
        'date' => '2026-06-16',
        'time_slots' => ['morning'],
    ]);

    $result = Availability::inTheFuture()->get();

    expect($result)->toHaveCount(1);
    expect($result->first()->id)->toBe($availability->id);
});

test('in the future scope excludes past dates', function () {
    $caregiver = createCaregiver();

    // Freeze time at 2026-06-17 12:00 UTC = 2026-06-17 05:00 PT (midday UTC = morning PT)
    $this->travelTo(CarbonImmutable::parse('2026-06-17 12:00:00', 'UTC'));

    Availability::factory()->create([
        'caregiver_id' => $caregiver->id,
        'date' => '2026-06-16',
        'time_slots' => ['morning'],
    ]);

    $result = Availability::inTheFuture()->get();

    expect($result)->toBeEmpty();
});
