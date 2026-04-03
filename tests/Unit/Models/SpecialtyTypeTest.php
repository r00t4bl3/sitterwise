<?php

use App\Models\SpecialtyType;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpecialtyTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_instantiated()
    {
        $specialty = SpecialtyType::factory()->make();

        $this->assertInstanceOf(SpecialtyType::class, $specialty);
    }

    public function test_has_correct_fillable_fields()
    {
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
    }

    public function test_casts_is_active_as_boolean()
    {
        $specialty = SpecialtyType::factory()->create(['is_active' => false]);

        $this->assertFalse($specialty->is_active);
        $this->assertIsBool($specialty->is_active);
    }

    public function test_defines_caregivers_relationship()
    {
        $specialty = SpecialtyType::factory()->make();
        $relation = $specialty->caregivers();

        $this->assertInstanceOf(BelongsToMany::class, $relation);
    }

    public function test_active_scope_returns_only_active_specialties()
    {
        SpecialtyType::factory()->create(['name' => 'Active Specialty', 'is_active' => true, 'sort_order' => 1]);
        SpecialtyType::factory()->create(['name' => 'Inactive Specialty', 'is_active' => false, 'sort_order' => 2]);

        $active = SpecialtyType::active()->get();

        $this->assertCount(1, $active);
        $this->assertEquals('Active Specialty', $active->first()->name);
    }
}
