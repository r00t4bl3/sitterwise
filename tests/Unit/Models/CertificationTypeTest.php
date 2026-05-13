<?php

use App\Models\CertificationType;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('can be instantiated', function () {
    $cert = CertificationType::factory()->make();

    $this->assertInstanceOf(CertificationType::class, $cert);
});

test('has correct fillable fields', function () {
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
});

test('casts expires required as boolean', function () {
    $cert = CertificationType::factory()->create(['expires_required' => false]);

    $this->assertFalse($cert->expires_required);
    $this->assertIsBool($cert->expires_required);
});

test('casts is active as boolean', function () {
    $cert = CertificationType::factory()->create(['is_active' => false]);

    $this->assertFalse($cert->is_active);
    $this->assertIsBool($cert->is_active);
});

test('defines caregivers relationship', function () {
    $cert = CertificationType::factory()->make();
    $relation = $cert->caregivers();

    $this->assertInstanceOf(BelongsToMany::class, $relation);
});

test('active scope returns only active certifications', function () {
    CertificationType::factory()->create(['name' => 'Active Cert', 'is_active' => true]);
    CertificationType::factory()->create(['name' => 'Inactive Cert', 'is_active' => false]);

    $active = CertificationType::active()->get();

    $this->assertCount(1, $active);
    $this->assertEquals('Active Cert', $active->first()->name);
});
