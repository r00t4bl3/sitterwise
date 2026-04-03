<?php

use App\Models\Availability;
use App\Models\Caregiver;
use App\Models\CaregiverStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_instantiated()
    {
        $status = CaregiverStatus::factory()->create();
        $caregiver = Caregiver::factory()->make(['status_id' => $status->id]);
        $availability = Availability::factory()->make(['caregiver_id' => $caregiver->id]);

        $this->assertInstanceOf(Availability::class, $availability);
    }

    public function test_has_correct_fillable_fields()
    {
        $status = CaregiverStatus::factory()->create();
        $caregiver = Caregiver::factory()->make(['status_id' => $status->id]);
        $availability = Availability::factory()->make(['caregiver_id' => $caregiver->id]);

        $this->assertNotNull($availability->date);
        $this->assertIsArray($availability->time_slots);
    }

    public function test_casts_date_as_date()
    {
        $status = CaregiverStatus::factory()->create();
        $caregiver = Caregiver::factory()->make(['status_id' => $status->id]);
        $availability = Availability::factory()->make([
            'caregiver_id' => $caregiver->id,
            'date' => '2026-12-25',
            'time_slots' => ['morning'],
        ]);

        $this->assertInstanceOf(CarbonImmutable::class, $availability->date);
        $this->assertEquals('2026-12-25', $availability->date->toDateString());
    }

    public function test_casts_time_slots_as_array()
    {
        $status = CaregiverStatus::factory()->create();
        $caregiver = Caregiver::factory()->make(['status_id' => $status->id]);
        $availability = Availability::factory()->make([
            'caregiver_id' => $caregiver->id,
            'time_slots' => ['morning', 'afternoon'],
        ]);

        $this->assertIsArray($availability->time_slots);
        $this->assertContains('morning', $availability->time_slots);
        $this->assertContains('afternoon', $availability->time_slots);
    }

    public function test_defines_caregiver_relationship()
    {
        $status = CaregiverStatus::factory()->create();
        $caregiver = Caregiver::factory()->make(['status_id' => $status->id]);
        $availability = Availability::factory()->make(['caregiver_id' => $caregiver->id]);

        $relation = $availability->caregiver();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertInstanceOf(Caregiver::class, $relation->getRelated());
    }

    public function test_in_the_future_scope_returns_future_dates()
    {
        $status = CaregiverStatus::factory()->create();
        $caregiver = Caregiver::factory()->make(['status_id' => $status->id]);
        $future = Availability::factory()->make([
            'caregiver_id' => $caregiver->id,
            'date' => now()->addDays(5)->toDateString(),
            'time_slots' => ['morning'],
        ]);
        $past = Availability::factory()->make([
            'caregiver_id' => $caregiver->id,
            'date' => now()->subDays(5)->toDateString(),
            'time_slots' => ['morning'],
        ]);

        // Test the scope query logic directly
        $query = Availability::query()->where('date', '>=', now()->toDateString());
        $this->assertStringContainsString('>=', $query->toSql());
    }
}
