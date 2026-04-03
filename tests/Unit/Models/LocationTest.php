<?php

use App\Models\Location;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_instantiated()
    {
        $location = Location::factory()->make();

        $this->assertInstanceOf(Location::class, $location);
    }

    public function test_has_correct_fillable_fields()
    {
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
    }

    public function test_casts_is_active_as_boolean()
    {
        $location = Location::factory()->create(['is_active' => false]);

        $this->assertFalse($location->is_active);
        $this->assertIsBool($location->is_active);
    }

    public function test_defines_caregivers_relationship()
    {
        $location = Location::factory()->make();
        $relation = $location->caregivers();

        $this->assertInstanceOf(BelongsToMany::class, $relation);
    }

    public function test_active_scope_returns_only_active_locations()
    {
        Location::factory()->create(['name' => 'Active Location', 'is_active' => true]);
        Location::factory()->create(['name' => 'Inactive Location', 'is_active' => false]);

        $active = Location::active()->get();

        $this->assertCount(1, $active);
        $this->assertEquals('Active Location', $active->first()->name);
    }
}
