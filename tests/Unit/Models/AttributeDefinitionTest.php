<?php

use App\Models\AttributeDefinition;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttributeDefinitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_instantiated()
    {
        $attribute = AttributeDefinition::factory()->make();

        $this->assertInstanceOf(AttributeDefinition::class, $attribute);
    }

    public function test_has_correct_fillable_fields()
    {
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
    }

    public function test_casts_options_as_array()
    {
        $attribute = AttributeDefinition::factory()->create([
            'options' => ['option1', 'option2'],
        ]);

        $this->assertIsArray($attribute->options);
    }

    public function test_casts_is_active_as_boolean()
    {
        $attribute = AttributeDefinition::factory()->create(['is_active' => false]);

        $this->assertFalse($attribute->is_active);
        $this->assertIsBool($attribute->is_active);
    }

    public function test_generates_slug_on_create()
    {
        $attribute = AttributeDefinition::factory()->state(['slug' => null])->create([
            'name' => 'Test Attribute Name',
        ]);

        $this->assertEquals('test-attribute-name', $attribute->slug);
    }

    public function test_generates_unique_slug_for_duplicate_names()
    {
        AttributeDefinition::factory()->state(['slug' => null])->create(['name' => 'Test Attribute']);
        $attribute2 = AttributeDefinition::factory()->state(['slug' => null])->create(['name' => 'Test Attribute']);

        $this->assertEquals('test-attribute_1', $attribute2->slug);
    }

    public function test_defines_caregivers_relationship()
    {
        $attribute = AttributeDefinition::factory()->make();
        $relation = $attribute->caregivers();

        $this->assertInstanceOf(BelongsToMany::class, $relation);
    }

    public function test_defines_clients_relationship()
    {
        $attribute = AttributeDefinition::factory()->make();
        $relation = $attribute->clients();

        $this->assertInstanceOf(BelongsToMany::class, $relation);
    }

    public function test_defines_bookings_relationship()
    {
        $attribute = AttributeDefinition::factory()->make();
        $relation = $attribute->bookings();

        $this->assertInstanceOf(BelongsToMany::class, $relation);
    }

    public function test_active_scope_returns_only_active_attributes()
    {
        AttributeDefinition::factory()->create(['name' => 'Active', 'is_active' => true, 'sort_order' => 1]);
        AttributeDefinition::factory()->create(['name' => 'Inactive', 'is_active' => false, 'sort_order' => 2]);

        $active = AttributeDefinition::active()->get();

        $this->assertCount(1, $active);
        $this->assertEquals('Active', $active->first()->name);
    }

    public function test_for_caregivers_scope()
    {
        AttributeDefinition::factory()->create(['name' => 'Caregiver Only', 'entity_type' => 'caregiver']);
        AttributeDefinition::factory()->create(['name' => 'Client Only', 'entity_type' => 'client']);
        AttributeDefinition::factory()->create(['name' => 'Both', 'entity_type' => 'both']);

        $caregiverAttrs = AttributeDefinition::forCaregivers()->get();

        $this->assertCount(2, $caregiverAttrs);
    }

    public function test_for_clients_scope()
    {
        AttributeDefinition::factory()->create(['name' => 'Caregiver Only', 'entity_type' => 'caregiver']);
        AttributeDefinition::factory()->create(['name' => 'Client Only', 'entity_type' => 'client']);
        AttributeDefinition::factory()->create(['name' => 'Both', 'entity_type' => 'both']);

        $clientAttrs = AttributeDefinition::forClients()->get();

        $this->assertCount(2, $clientAttrs);
    }

    public function test_for_bookings_scope()
    {
        AttributeDefinition::factory()->create(['name' => 'Booking Only', 'entity_type' => 'booking']);
        AttributeDefinition::factory()->create(['name' => 'Client Only', 'entity_type' => 'client']);
        AttributeDefinition::factory()->create(['name' => 'Both', 'entity_type' => 'both']);

        $bookingAttrs = AttributeDefinition::forBookings()->get();

        $this->assertCount(2, $bookingAttrs);
    }
}
