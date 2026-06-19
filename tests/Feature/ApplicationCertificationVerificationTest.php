<?php

use App\Enums\CaregiverStatus;
use App\Models\Caregiver;
use App\Models\CaregiverApplication;
use App\Models\CertificationType;
use App\Models\OnboardingChecklistItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function certificationVerificationCreateApplication(): array
{
    $user = User::factory()->create(['role' => 'caregiver', 'email' => 'certs@example.com']);
    $caregiver = Caregiver::create([
        'user_id' => $user->id,
        'status' => CaregiverStatus::UnderReview->value,
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'slug' => 'jane-smith-'.Str::random(4),
        'phone' => '555-1234',
        'date_of_birth' => '1990-01-01',
    ]);

    $application = CaregiverApplication::create([
        'caregiver_id' => $caregiver->id,
        'data' => [
            'personal' => [
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'phone' => '555-1234',
                'dob' => '1990-01-01',
                'address_line1' => '123 Main St',
                'address_city' => 'San Diego',
                'address_state' => 'CA',
            ],
            'sponsor' => [
                'first_name' => 'Sponsor',
                'last_name' => 'Person',
                'email' => 'sponsor@example.com',
                'relationship' => 'Friend',
            ],
        ],
        'submitted_at' => now(),
    ]);

    return [
        'application' => $application,
        'caregiver' => $caregiver,
        'user' => $user,
    ];
}

describe('Application Certification Verification', function () {
    it('verifies an unverified certification', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $data = certificationVerificationCreateApplication();
        $certType = CertificationType::factory()->create(['name' => 'CPR & First Aid']);

        $data['caregiver']->certifications()->attach($certType->id, [
            'verified_at' => null,
            'expiration_date' => now()->addYear(),
        ]);

        $response = $this->post("/applications/{$data['application']->id}/certifications/{$certType->id}/verify");

        $response->assertSessionDoesntHaveErrors();
        $this->assertNotNull(
            $data['caregiver']->certifications()->where('certification_type_id', $certType->id)->first()->pivot->verified_at
        );
    });

    it('unverifies a verified certification', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $data = certificationVerificationCreateApplication();
        $certType = CertificationType::factory()->create(['name' => 'CPR & First Aid']);

        $data['caregiver']->certifications()->attach($certType->id, [
            'verified_at' => now(),
            'expiration_date' => now()->addYear(),
        ]);

        $response = $this->post("/applications/{$data['application']->id}/certifications/{$certType->id}/verify");

        $response->assertSessionDoesntHaveErrors();
        $this->assertNull(
            $data['caregiver']->certifications()->where('certification_type_id', $certType->id)->first()->pivot->verified_at
        );
    });

    it('returns 422 when caregiver does not have the certification type', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $data = certificationVerificationCreateApplication();
        $certType = CertificationType::factory()->create();

        $response = $this->post(
            "/applications/{$data['application']->id}/certifications/{$certType->id}/verify",
            [],
        );

        $response->assertStatus(422);
    });

    it('redirects guest users to login', function () {
        $data = certificationVerificationCreateApplication();
        $certType = CertificationType::factory()->create();

        $response = $this->post(
            "/applications/{$data['application']->id}/certifications/{$certType->id}/verify",
        );

        $response->assertRedirect('/login');
    });

    it('returns 403 for non-admin users', function () {
        $caregiverUser = User::factory()->create(['role' => 'caregiver']);
        $this->actingAs($caregiverUser);

        $data = certificationVerificationCreateApplication();
        $certType = CertificationType::factory()->create();

        $response = $this->post(
            "/applications/{$data['application']->id}/certifications/{$certType->id}/verify",
        );

        $response->assertStatus(403);
    });

    it('syncs checklist item when toggling CPR & First Aid', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $data = certificationVerificationCreateApplication();
        $caregiver = $data['caregiver'];
        $certType = CertificationType::factory()->create(['name' => 'CPR & First Aid']);

        $caregiver->certifications()->attach($certType->id, [
            'verified_at' => null,
            'expiration_date' => now()->addYear(),
        ]);

        // Seed onboarding checklist
        OnboardingChecklistItem::seedForCaregiver($caregiver);

        $checklistItem = $caregiver->onboardingChecklistItems()
            ->where('item_key', 'cpr_uploaded')
            ->first();

        expect($checklistItem->completed_at)->toBeNull();

        $this->post("/applications/{$data['application']->id}/certifications/{$certType->id}/verify");

        $checklistItem->refresh();
        expect($checklistItem->completed_at)->not->toBeNull();
    });

    it('unsyncs checklist item when unverifying CPR & First Aid', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $data = certificationVerificationCreateApplication();
        $caregiver = $data['caregiver'];
        $certType = CertificationType::factory()->create(['name' => 'CPR & First Aid']);

        $caregiver->certifications()->attach($certType->id, [
            'verified_at' => now(),
            'expiration_date' => now()->addYear(),
        ]);

        OnboardingChecklistItem::seedForCaregiver($caregiver);

        $checklistItem = $caregiver->onboardingChecklistItems()
            ->where('item_key', 'cpr_uploaded')
            ->first();

        $checklistItem->update(['completed_at' => now()]);

        $this->post("/applications/{$data['application']->id}/certifications/{$certType->id}/verify");

        $checklistItem->refresh();
        expect($checklistItem->completed_at)->toBeNull();
    });

    it('does not sync checklist for cert types without mapping', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $data = certificationVerificationCreateApplication();
        $caregiver = $data['caregiver'];
        $certType = CertificationType::factory()->create(['name' => 'Food Handler']);

        $caregiver->certifications()->attach($certType->id, [
            'verified_at' => null,
            'expiration_date' => now()->addYear(),
        ]);

        OnboardingChecklistItem::seedForCaregiver($caregiver);

        $this->post("/applications/{$data['application']->id}/certifications/{$certType->id}/verify");

        // No checklist items should have been affected
        $completed = $caregiver->onboardingChecklistItems()
            ->whereNotNull('completed_at')
            ->count();

        expect($completed)->toBe(0);
    });

    it('syncs checklist for Trustline cert type', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $data = certificationVerificationCreateApplication();
        $caregiver = $data['caregiver'];
        $certType = CertificationType::factory()->create(['name' => 'Trustline']);

        $caregiver->certifications()->attach($certType->id, [
            'verified_at' => null,
            'expiration_date' => now()->addYear(),
        ]);

        OnboardingChecklistItem::seedForCaregiver($caregiver);

        $this->post("/applications/{$data['application']->id}/certifications/{$certType->id}/verify");

        $checklistItem = $caregiver->onboardingChecklistItems()
            ->where('item_key', 'trustline_submitted')
            ->first();

        expect($checklistItem->completed_at)->not->toBeNull();
    });
});
