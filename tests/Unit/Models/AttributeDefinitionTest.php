<?php

use App\Models\AttributeDefinition;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('can be instantiated', function () {
    $attribute = AttributeDefinition::factory()->make();

    $this->assertInstanceOf(AttributeDefinition::class, $attribute);
});

test('has correct fillable fields', function () {
    $attribute = AttributeDefinition::factory()->create([
        'name' => 'Test Attribute',
        'type' => 'boolean',
        'entity_type' => 'caregiver',
        'is_active' => true,
        'sort_order' => 5,
    ]);

    $this->assertEquals('Test Attribute', $attribute->name);
    $this->assertEquals('boolean', $attribute->type);
    $this->assertEquals('caregiver', $attribute->entity_type);
    $this->assertTrue($attribute->is_active);
    $this->assertEquals(5, $attribute->sort_order);
});

test('casts options as array', function () {
    $attribute = AttributeDefinition::factory()->create([
        'options' => ['option1', 'option2'],
    ]);

    $this->assertIsArray($attribute->options);
});

test('casts is active as boolean', function () {
    $attribute = AttributeDefinition::factory()->create(['is_active' => false]);

    $this->assertFalse($attribute->is_active);
    $this->assertIsBool($attribute->is_active);
});

test('generates slug on create', function () {
    $attribute = AttributeDefinition::factory()->state(['slug' => null])->create([
        'name' => 'Test Attribute Name',
    ]);

    $this->assertEquals('test-attribute-name', $attribute->slug);
});

test('generates unique slug for duplicate names', function () {
    AttributeDefinition::factory()->state(['slug' => null])->create(['name' => 'Test Attribute']);
    $attribute2 = AttributeDefinition::factory()->state(['slug' => null])->create(['name' => 'Test Attribute']);

    $this->assertEquals('test-attribute_1', $attribute2->slug);
});

test('defines caregivers relationship', function () {
    $attribute = AttributeDefinition::factory()->make();
    $relation = $attribute->caregivers();

    $this->assertInstanceOf(BelongsToMany::class, $relation);
});

test('defines clients relationship', function () {
    $attribute = AttributeDefinition::factory()->make();
    $relation = $attribute->clients();

    $this->assertInstanceOf(BelongsToMany::class, $relation);
});

test('defines bookings relationship', function () {
    $attribute = AttributeDefinition::factory()->make();
    $relation = $attribute->bookings();

    $this->assertInstanceOf(BelongsToMany::class, $relation);
});

test('active scope returns only active attributes', function () {
    AttributeDefinition::factory()->create(['name' => 'Active', 'is_active' => true, 'sort_order' => 1]);
    AttributeDefinition::factory()->create(['name' => 'Inactive', 'is_active' => false, 'sort_order' => 2]);

    $active = AttributeDefinition::active()->get();

    $this->assertCount(1, $active);
    $this->assertEquals('Active', $active->first()->name);
});

test('for caregivers scope', function () {
    AttributeDefinition::factory()->create(['name' => 'Caregiver Only', 'entity_type' => 'caregiver']);
    AttributeDefinition::factory()->create(['name' => 'Client Only', 'entity_type' => 'client']);
    AttributeDefinition::factory()->create(['name' => 'Both', 'entity_type' => 'both']);

    $caregiverAttrs = AttributeDefinition::forCaregivers()->get();

    $this->assertCount(2, $caregiverAttrs);
});

test('for clients scope', function () {
    AttributeDefinition::factory()->create(['name' => 'Caregiver Only', 'entity_type' => 'caregiver']);
    AttributeDefinition::factory()->create(['name' => 'Client Only', 'entity_type' => 'client']);
    AttributeDefinition::factory()->create(['name' => 'Both', 'entity_type' => 'both']);

    $clientAttrs = AttributeDefinition::forClients()->get();

    $this->assertCount(2, $clientAttrs);
});

test('for bookings scope', function () {
    AttributeDefinition::factory()->create(['name' => 'Booking Only', 'entity_type' => 'booking']);
    AttributeDefinition::factory()->create(['name' => 'Client Only', 'entity_type' => 'client']);
    AttributeDefinition::factory()->create(['name' => 'Both', 'entity_type' => 'both']);

    $bookingAttrs = AttributeDefinition::forBookings()->get();

    $this->assertCount(2, $bookingAttrs);
});
