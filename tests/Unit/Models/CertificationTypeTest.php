<?php

use App\Models\CertificationType;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificationTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_instantiated()
    {
        $cert = CertificationType::factory()->make();

        $this->assertInstanceOf(CertificationType::class, $cert);
    }

    public function test_has_correct_fillable_fields()
    {
        $cert = CertificationType::factory()->create([
            'name' => 'CPR Certified',
            'description' => 'Cardiopulmonary resuscitation certification',
            'expires_required' => true,
            'is_active' => true,
        ]);

        $this->assertEquals('CPR Certified', $cert->name);
        $this->assertEquals('Cardiopulmonary resuscitation certification', $cert->description);
        $this->assertTrue($cert->expires_required);
        $this->assertTrue($cert->is_active);
    }

    public function test_casts_expires_required_as_boolean()
    {
        $cert = CertificationType::factory()->create(['expires_required' => false]);

        $this->assertFalse($cert->expires_required);
        $this->assertIsBool($cert->expires_required);
    }

    public function test_casts_is_active_as_boolean()
    {
        $cert = CertificationType::factory()->create(['is_active' => false]);

        $this->assertFalse($cert->is_active);
        $this->assertIsBool($cert->is_active);
    }

    public function test_defines_caregivers_relationship()
    {
        $cert = CertificationType::factory()->make();
        $relation = $cert->caregivers();

        $this->assertInstanceOf(BelongsToMany::class, $relation);
    }

    public function test_active_scope_returns_only_active_certifications()
    {
        CertificationType::factory()->create(['name' => 'Active Cert', 'is_active' => true]);
        CertificationType::factory()->create(['name' => 'Inactive Cert', 'is_active' => false]);

        $active = CertificationType::active()->get();

        $this->assertCount(1, $active);
        $this->assertEquals('Active Cert', $active->first()->name);
    }
}
