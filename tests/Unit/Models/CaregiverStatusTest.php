<?php

use App\Models\Caregiver;
use App\Models\CaregiverStatus;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('can be instantiated', function () {
    $status = CaregiverStatus::factory()->make();

    $this->assertInstanceOf(CaregiverStatus::class, $status);
});

test('has correct fillable fields', function () {
    $status = CaregiverStatus::factory()->create([
        'name' => 'Test Status',
        'description' => 'A test status',
        'color' => '#FF0000',
        'is_active' => true,
        'sort_order' => 5,
    ]);

    $this->assertEquals('Test Status', $status->name);
    $this->assertEquals('A test status', $status->description);
    $this->assertEquals('#FF0000', $status->color);
    $this->assertTrue($status->is_active);
    $this->assertEquals(5, $status->sort_order);
});

test('casts is active as boolean', function () {
    $status = CaregiverStatus::factory()->create(['is_active' => false]);

    $this->assertFalse($status->is_active);
    $this->assertIsBool($status->is_active);
});

test('defines caregivers relationship', function () {
    $status = CaregiverStatus::factory()->create();

    $relation = $status->caregivers();

    $this->assertInstanceOf(HasMany::class, $relation);
    $this->assertInstanceOf(Caregiver::class, $relation->getRelated());
});

test('active scope returns only active statuses', function () {
    CaregiverStatus::factory()->create(['name' => 'Active', 'is_active' => true]);
    CaregiverStatus::factory()->create(['name' => 'Inactive', 'is_active' => false]);

    $activeStatuses = CaregiverStatus::active()->get();

    $this->assertCount(1, $activeStatuses);
    $this->assertEquals('Active', $activeStatuses->first()->name);
});
