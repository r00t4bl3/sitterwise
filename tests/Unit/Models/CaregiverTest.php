<?php

use App\Enums\CaregiverStatus;
use App\Models\Availability;
use App\Models\Booking;
use App\Models\Caregiver;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('can be instantiated', function () {
    $caregiver = Caregiver::factory()->make(['status' => CaregiverStatus::Active->value]);

    $this->assertInstanceOf(Caregiver::class, $caregiver);
});

test('has correct fillable fields', function () {
    $user = User::factory()->create();
    $caregiver = Caregiver::factory()->make([
        'user_id' => $user->id,
        'status' => CaregiverStatus::Active->value,
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'phone' => '555-1234',
        'address_line1' => '123 Main St',
        'address_line2' => null,
        'address_city' => 'San Diego',
        'address_state' => 'CA',
        'address_zip' => '92101',
        'date_of_birth' => '1990-01-15',
        'rating' => 4.50,
        'biography' => 'Experienced caregiver',
        'notes' => 'Some notes',
    ]);

    $this->assertEquals('Jane', $caregiver->first_name);
    $this->assertEquals('Smith', $caregiver->last_name);
    $this->assertEquals('555-1234', $caregiver->phone);
    $this->assertEquals('123 Main St', $caregiver->address_line1);
    $this->assertEquals('San Diego', $caregiver->address_city);
    $this->assertEquals('CA', $caregiver->address_state);
    $this->assertEquals('92101', $caregiver->address_zip);
    $this->assertEquals(4.50, $caregiver->rating);
    $this->assertEquals('Experienced caregiver', $caregiver->biography);
    $this->assertEquals('Some notes', $caregiver->notes);
});

test('casts date of birth as date', function () {
    $caregiver = Caregiver::factory()->make([
        'status' => CaregiverStatus::Active->value,
        'date_of_birth' => '1990-01-15',
    ]);

    $this->assertInstanceOf(CarbonImmutable::class, $caregiver->date_of_birth);
    $this->assertEquals('1990-01-15', $caregiver->date_of_birth->toDateString());
});

test('casts rating as decimal', function () {
    $caregiver = Caregiver::factory()->make([
        'status' => CaregiverStatus::Active->value,
        'rating' => 4.75,
    ]);

    $this->assertEquals(4.75, $caregiver->rating);
});

test('defines user relationship', function () {
    $user = User::factory()->create();
    $caregiver = Caregiver::factory()->make([
        'user_id' => $user->id,
        'status' => CaregiverStatus::Active->value,
    ]);

    $relation = $caregiver->user();

    $this->assertInstanceOf(BelongsTo::class, $relation);
    $this->assertInstanceOf(User::class, $relation->getRelated());
});

test('casts status as enum', function () {
    $caregiver = Caregiver::factory()->make(['status' => CaregiverStatus::Active->value]);

    $this->assertInstanceOf(CaregiverStatus::class, $caregiver->status);
    $this->assertEquals(CaregiverStatus::Active, $caregiver->status);
});

test('defines availability relationship', function () {
    $caregiver = Caregiver::factory()->make(['status' => CaregiverStatus::Active->value]);

    $relation = $caregiver->availabilities();

    $this->assertInstanceOf(HasMany::class, $relation);
    $this->assertInstanceOf(Availability::class, $relation->getRelated());
});

test('defines bookings relationship', function () {
    $caregiver = Caregiver::factory()->make(['status' => CaregiverStatus::Active->value]);

    $relation = $caregiver->bookings();

    $this->assertInstanceOf(HasMany::class, $relation);
    $this->assertInstanceOf(Booking::class, $relation->getRelated());
});

test('defines attribute definitions relationship', function () {
    $caregiver = Caregiver::factory()->make(['status' => CaregiverStatus::Active->value]);

    $relation = $caregiver->attributes();

    $this->assertInstanceOf(BelongsToMany::class, $relation);
});

test('returns full name', function () {
    $caregiver = Caregiver::factory()->make([
        'status' => CaregiverStatus::Active->value,
        'first_name' => 'Jane',
        'last_name' => 'Smith',
    ]);

    $this->assertEquals('Jane Smith', $caregiver->full_name);
});

test('syncs user name on save', function () {
    $user = User::factory()->create(['name' => 'Original Name']);
    // Create caregiver manually to avoid factory's configure() method
    $caregiver = new Caregiver([
        'user_id' => $user->id,
        'status' => CaregiverStatus::Active->value,
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'slug' => 'jane-smith-'.rand(10000, 99999),
    ]);
    $caregiver->save();

    $user->refresh();
    $this->assertEquals('Jane Smith', $user->name);
});

test('preferred locations scope', function () {
    $caregiver = Caregiver::factory()->make(['status' => CaregiverStatus::Active->value]);

    $relation = $caregiver->preferredLocations();

    $this->assertInstanceOf(BelongsToMany::class, $relation);
});

test('willing locations scope', function () {
    $caregiver = Caregiver::factory()->make(['status' => CaregiverStatus::Active->value]);

    $relation = $caregiver->willingLocations();

    $this->assertInstanceOf(BelongsToMany::class, $relation);
});

test('defines blocked clients relationship', function () {
    $caregiver = Caregiver::factory()->make(['status' => CaregiverStatus::Active->value]);

    $relation = $caregiver->blockedClients();

    $this->assertInstanceOf(BelongsToMany::class, $relation);
});

test('generates slug with last initial', function () {
    $caregiver = Caregiver::factory()->make([
        'status' => CaregiverStatus::Active->value,
        'first_name' => 'Jason',
        'last_name' => 'Statham',
        'slug' => '',
    ]);
    $caregiver->save();

    $this->assertEquals('jason-s', $caregiver->refresh()->slug);
});

test('generates unique slug with full last name on collision', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $caregiver1 = new Caregiver([
        'user_id' => $user1->id,
        'status' => CaregiverStatus::Active->value,
        'first_name' => 'Jason',
        'last_name' => 'Momoa',
        'slug' => '',
    ]);
    $caregiver1->save();

    $caregiver2 = new Caregiver([
        'user_id' => $user2->id,
        'status' => CaregiverStatus::Active->value,
        'first_name' => 'Jason',
        'last_name' => 'Michael',
        'slug' => '',
    ]);
    $caregiver2->save();

    $this->assertEquals('jason-m', $caregiver1->refresh()->slug);
    $this->assertEquals('jason-michael', $caregiver2->refresh()->slug);
});

test('falls back to numeric suffix when full last name also collides', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $user3 = User::factory()->create();

    $caregiver1 = new Caregiver([
        'user_id' => $user1->id,
        'status' => CaregiverStatus::Active->value,
        'first_name' => 'Jason',
        'last_name' => 'Momoa',
        'slug' => '',
    ]);
    $caregiver1->save();

    $caregiver2 = new Caregiver([
        'user_id' => $user2->id,
        'status' => CaregiverStatus::Active->value,
        'first_name' => 'Jason',
        'last_name' => 'Michael',
        'slug' => '',
    ]);
    $caregiver2->save();

    $caregiver3 = new Caregiver([
        'user_id' => $user3->id,
        'status' => CaregiverStatus::Active->value,
        'first_name' => 'Jason',
        'last_name' => 'Miller',
        'slug' => '',
    ]);
    $caregiver3->save();

    $this->assertEquals('jason-m', $caregiver1->refresh()->slug);
    $this->assertEquals('jason-michael', $caregiver2->refresh()->slug);
    $this->assertEquals('jason-miller', $caregiver3->refresh()->slug);
});

test('generates slug for special last name', function () {
    $caregiver = Caregiver::factory()->make([
        'status' => CaregiverStatus::Active->value,
        'first_name' => 'Jason',
        'last_name' => "O'Brien",
        'slug' => '',
    ]);
    $caregiver->save();

    $this->assertEquals('jason-o', $caregiver->refresh()->slug);
});

test('uses numeric suffix when full last name also collides', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $user3 = User::factory()->create();

    $caregiver1 = new Caregiver([
        'user_id' => $user1->id,
        'status' => CaregiverStatus::Active->value,
        'first_name' => 'Jason',
        'last_name' => 'Momoa',
        'slug' => 'jason-m',
    ]);
    $caregiver1->save();

    $caregiver2 = new Caregiver([
        'user_id' => $user2->id,
        'status' => CaregiverStatus::Active->value,
        'first_name' => 'Jason',
        'last_name' => 'Momoa',
        'slug' => 'jason-momoa',
    ]);
    $caregiver2->save();

    $caregiver3 = new Caregiver([
        'user_id' => $user3->id,
        'status' => CaregiverStatus::Active->value,
        'first_name' => 'Jason',
        'last_name' => 'Momoa',
        'slug' => '',
    ]);
    $caregiver3->save();

    $this->assertEquals('jason-momoa-2', $caregiver3->refresh()->slug);
});
