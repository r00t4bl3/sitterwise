<?php

use App\Models\Hotel;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('can be instantiated', function () {
    $hotel = Hotel::factory()->make();

    $this->assertInstanceOf(Hotel::class, $hotel);
});

test('has correct fillable fields', function () {
    $hotel = Hotel::factory()->create([
        'name' => 'Test Hotel',
        'line1' => '123 Hotel St',
        'city' => 'San Diego',
        'state' => 'CA',
        'zip' => '92101',
        'hourly_rate' => 25.00,
        'resort_fee' => 15.00,
        'is_active' => true,
    ]);

    $this->assertEquals('Test Hotel', $hotel->name);
    $this->assertEquals('123 Hotel St', $hotel->line1);
    $this->assertEquals('San Diego', $hotel->city);
    $this->assertEquals('CA', $hotel->state);
    $this->assertEquals('92101', $hotel->zip);
    $this->assertEquals(25.00, $hotel->hourly_rate);
    $this->assertEquals(15.00, $hotel->resort_fee);
    $this->assertTrue($hotel->is_active);
});

test('casts is active as boolean', function () {
    $hotel = Hotel::factory()->create(['is_active' => true]);

    $this->assertTrue($hotel->is_active);
    $this->assertIsBool($hotel->is_active);
});

test('casts hourly rate as decimal', function () {
    $hotel = Hotel::factory()->create(['hourly_rate' => 30.50]);

    $this->assertEquals(30.50, $hotel->hourly_rate);
});

test('casts resort fee as decimal', function () {
    $hotel = Hotel::factory()->create(['resort_fee' => 20.75]);

    $this->assertEquals(20.75, $hotel->resort_fee);
});

test('active scope returns only active hotels', function () {
    Hotel::factory()->create(['name' => 'Active Hotel', 'is_active' => true]);
    Hotel::factory()->create(['name' => 'Inactive Hotel', 'is_active' => false]);

    $activeHotels = Hotel::active()->get();

    $this->assertCount(1, $activeHotels);
    $this->assertEquals('Active Hotel', $activeHotels->first()->name);
});
