<?php

use App\Models\Location;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('can be instantiated', function () {
    $location = Location::factory()->make();

    $this->assertInstanceOf(Location::class, $location);
});

test('has correct fillable fields', function () {
    $location = Location::factory()->create([
        'name' => 'San Diego',
        'description' => 'Beautiful coastal city',
        'svg_icon' => '<svg></svg>',
        'is_active' => true,
    ]);

    $this->assertEquals('San Diego', $location->name);
    $this->assertEquals('Beautiful coastal city', $location->description);
    $this->assertEquals('<svg></svg>', $location->svg_icon);
    $this->assertTrue($location->is_active);
});

test('casts is active as boolean', function () {
    $location = Location::factory()->create(['is_active' => false]);

    $this->assertFalse($location->is_active);
    $this->assertIsBool($location->is_active);
});

test('defines caregivers relationship', function () {
    $location = Location::factory()->make();
    $relation = $location->caregivers();

    $this->assertInstanceOf(BelongsToMany::class, $relation);
});

test('active scope returns only active locations', function () {
    Location::factory()->create(['name' => 'Active Location', 'is_active' => true]);
    Location::factory()->create(['name' => 'Inactive Location', 'is_active' => false]);

    $active = Location::active()->get();

    $this->assertCount(1, $active);
    $this->assertEquals('Active Location', $active->first()->name);
});
