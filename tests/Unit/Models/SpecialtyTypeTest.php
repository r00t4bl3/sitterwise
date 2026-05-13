<?php

use App\Models\SpecialtyType;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('can be instantiated', function () {
    $specialty = SpecialtyType::factory()->make();

    $this->assertInstanceOf(SpecialtyType::class, $specialty);
});

test('has correct fillable fields', function () {
    $specialty = SpecialtyType::factory()->create([
        'name' => 'Baby Specialist',
        'description' => 'Specializes in newborns',
        'is_active' => true,
        'sort_order' => 3,
    ]);

    $this->assertEquals('Baby Specialist', $specialty->name);
    $this->assertEquals('Specializes in newborns', $specialty->description);
    $this->assertTrue($specialty->is_active);
    $this->assertEquals(3, $specialty->sort_order);
});

test('casts is active as boolean', function () {
    $specialty = SpecialtyType::factory()->create(['is_active' => false]);

    $this->assertFalse($specialty->is_active);
    $this->assertIsBool($specialty->is_active);
});

test('defines caregivers relationship', function () {
    $specialty = SpecialtyType::factory()->make();
    $relation = $specialty->caregivers();

    $this->assertInstanceOf(BelongsToMany::class, $relation);
});

test('active scope returns only active specialties', function () {
    SpecialtyType::factory()->create(['name' => 'Active Specialty', 'is_active' => true, 'sort_order' => 1]);
    SpecialtyType::factory()->create(['name' => 'Inactive Specialty', 'is_active' => false, 'sort_order' => 2]);

    $active = SpecialtyType::active()->get();

    $this->assertCount(1, $active);
    $this->assertEquals('Active Specialty', $active->first()->name);
});
