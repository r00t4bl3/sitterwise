<?php

use App\Enums\CaregiverStatus;
use App\Models\Caregiver;
use App\Models\CaregiverApplication;
use App\Models\ReferenceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function referenceUpdateCreateApplication(): CaregiverApplication
{
    $user = User::factory()->create(['role' => 'caregiver', 'email' => 'caregiver@example.com']);
    $caregiver = Caregiver::create([
        'user_id' => $user->id,
        'status' => CaregiverStatus::Applicant->value,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'slug' => 'jane-doe-'.Str::random(6),
        'phone' => '555-1234',
        'date_of_birth' => '1990-01-01',
    ]);

    $application = CaregiverApplication::create([
        'caregiver_id' => $caregiver->id,
        'data' => ['personal' => ['first_name' => 'Jane', 'last_name' => 'Doe']],
        'submitted_at' => now(),
    ]);

    return $application;
}

function referenceUpdateCreateReference(CaregiverApplication $application): ReferenceRequest
{
    return ReferenceRequest::create([
        'token' => Str::random(32),
        'caregiver_id' => $application->caregiver_id,
        'reference_name' => 'Jane Reference',
        'reference_email' => 'jane@example.com',
        'relationship' => 'Former Employer',
        'years_known' => '3-5',
        'is_sponsor' => false,
    ]);
}

function referenceUpdateValidPayload(array $overrides = []): array
{
    return array_merge([
        'reference_name' => 'Jane Reference',
        'reference_email' => 'jane@example.com',
        'relationship' => 'Former Supervisor',
        'years_known' => '5-10',
        'is_sponsor' => false,
        'rating_reliability' => 5,
        'rating_trustworthiness' => 5,
        'rating_maturity' => 4,
        'rating_communication' => 5,
        'rating_warmth' => 4,
        'rating_overall_recommendation' => 5,
        'rating_appearance' => 5,
        'rating_punctuality' => 4,
        'strengths' => 'Very responsible and caring.',
        'concerns' => '',
        'additional_comments' => '',
        'background_drug_alcohol' => 'no',
        'background_tobacco' => 'no',
        'trust_own_child' => 'yes',
        'reason_not_care' => 'no',
        'reason_not_care_explanation' => '',
    ], $overrides);
}

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->application = referenceUpdateCreateApplication();
    $this->reference = referenceUpdateCreateReference($this->application);
});

describe('Admin reference update', function () {
    it('saves all new fields when updating a reference', function () {
        actingAs($this->admin)
            ->patch(
                "/applications/{$this->application->id}/references/{$this->reference->id}",
                referenceUpdateValidPayload(),
            )
            ->assertSessionHas('success');

        $this->reference->refresh();

        expect($this->reference->rating_appearance)->toBe(5);
        expect($this->reference->rating_punctuality)->toBe(4);
        expect($this->reference->background_drug_alcohol)->toBe('no');
        expect($this->reference->background_tobacco)->toBe('no');
        expect($this->reference->trust_own_child)->toBe('yes');
        expect($this->reference->reason_not_care)->toBe('no');
        expect($this->reference->reason_not_care_explanation)->toBeNull();
    });

    it('auto-submits reference when response data is provided', function () {
        actingAs($this->admin)
            ->patch(
                "/applications/{$this->application->id}/references/{$this->reference->id}",
                referenceUpdateValidPayload(),
            );

        $this->reference->refresh();
        expect($this->reference->submitted_at)->not->toBeNull();
    });

    it('validates background_drug_alcohol must be yes or no', function () {
        actingAs($this->admin)
            ->patch(
                "/applications/{$this->application->id}/references/{$this->reference->id}",
                referenceUpdateValidPayload(['background_drug_alcohol' => 'maybe']),
            )
            ->assertSessionHasErrors('background_drug_alcohol');
    });

    it('validates background_tobacco must be yes or no', function () {
        actingAs($this->admin)
            ->patch(
                "/applications/{$this->application->id}/references/{$this->reference->id}",
                referenceUpdateValidPayload(['background_tobacco' => 'sometimes']),
            )
            ->assertSessionHasErrors('background_tobacco');
    });

    it('validates trust_own_child must be yes, no, or unsure', function () {
        actingAs($this->admin)
            ->patch(
                "/applications/{$this->application->id}/references/{$this->reference->id}",
                referenceUpdateValidPayload(['trust_own_child' => 'maybe']),
            )
            ->assertSessionHasErrors('trust_own_child');
    });

    it('validates reason_not_care must be yes or no', function () {
        actingAs($this->admin)
            ->patch(
                "/applications/{$this->application->id}/references/{$this->reference->id}",
                referenceUpdateValidPayload(['reason_not_care' => 'maybe']),
            )
            ->assertSessionHasErrors('reason_not_care');
    });

    it('validates rating_appearance must be between 1 and 5', function () {
        actingAs($this->admin)
            ->patch(
                "/applications/{$this->application->id}/references/{$this->reference->id}",
                referenceUpdateValidPayload(['rating_appearance' => 6]),
            )
            ->assertSessionHasErrors('rating_appearance');
    });

    it('validates rating_punctuality must be between 1 and 5', function () {
        actingAs($this->admin)
            ->patch(
                "/applications/{$this->application->id}/references/{$this->reference->id}",
                referenceUpdateValidPayload(['rating_punctuality' => 0]),
            )
            ->assertSessionHasErrors('rating_punctuality');
    });

    it('can set new fields to null', function () {
        $this->reference->update([
            'rating_appearance' => 5,
            'rating_punctuality' => 4,
            'background_drug_alcohol' => 'no',
            'background_tobacco' => 'no',
            'trust_own_child' => 'yes',
            'reason_not_care' => 'no',
            'reason_not_care_explanation' => 'Some explanation',
            'submitted_at' => now(),
        ]);

        actingAs($this->admin)
            ->patch(
                "/applications/{$this->application->id}/references/{$this->reference->id}",
                referenceUpdateValidPayload([
                    'rating_appearance' => null,
                    'rating_punctuality' => null,
                    'background_drug_alcohol' => null,
                    'background_tobacco' => null,
                    'trust_own_child' => null,
                    'reason_not_care' => null,
                    'reason_not_care_explanation' => null,
                ]),
            )
            ->assertSessionHas('success');

        $this->reference->refresh();

        expect($this->reference->rating_appearance)->toBeNull();
        expect($this->reference->rating_punctuality)->toBeNull();
        expect($this->reference->background_drug_alcohol)->toBeNull();
        expect($this->reference->background_tobacco)->toBeNull();
        expect($this->reference->trust_own_child)->toBeNull();
        expect($this->reference->reason_not_care)->toBeNull();
        expect($this->reference->reason_not_care_explanation)->toBeNull();
    });

    it('requires authentication', function () {
        $this->patch(
            "/applications/{$this->application->id}/references/{$this->reference->id}",
            referenceUpdateValidPayload(),
        )->assertRedirect('/login');
    });
});
