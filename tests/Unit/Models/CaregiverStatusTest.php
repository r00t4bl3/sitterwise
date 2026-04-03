<?php

use App\Models\Caregiver;
use App\Models\CaregiverStatus;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaregiverStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_instantiated()
    {
        $status = CaregiverStatus::factory()->make();

        $this->assertInstanceOf(CaregiverStatus::class, $status);
    }

    public function test_has_correct_fillable_fields()
    {
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
    }

    public function test_casts_is_active_as_boolean()
    {
        $status = CaregiverStatus::factory()->create(['is_active' => false]);

        $this->assertFalse($status->is_active);
        $this->assertIsBool($status->is_active);
    }

    public function test_defines_caregivers_relationship()
    {
        $status = CaregiverStatus::factory()->create();

        $relation = $status->caregivers();

        $this->assertInstanceOf(HasMany::class, $relation);
        $this->assertInstanceOf(Caregiver::class, $relation->getRelated());
    }

    public function test_active_scope_returns_only_active_statuses()
    {
        CaregiverStatus::factory()->create(['name' => 'Active', 'is_active' => true]);
        CaregiverStatus::factory()->create(['name' => 'Inactive', 'is_active' => false]);

        $activeStatuses = CaregiverStatus::active()->get();

        $this->assertCount(1, $activeStatuses);
        $this->assertEquals('Active', $activeStatuses->first()->name);
    }
}
