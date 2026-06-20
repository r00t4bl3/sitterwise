<?php

use App\Enums\CaregiverStatus;
use App\Models\Caregiver;
use App\Models\ReferenceRequest;
use App\Models\User;
use App\Notifications\ReferenceCompletedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function referencePortalCreateReferenceRequest(?string $token = null): ReferenceRequest
{
    $user = User::factory()->create(['role' => 'caregiver']);
    $caregiver = Caregiver::create([
        'user_id' => $user->id,
        'status' => CaregiverStatus::Applicant->value,
        'first_name' => 'John',
        'last_name' => 'Doe',
        'slug' => 'john-doe-'.Str::random(6),
        'phone' => '555-1234',
        'date_of_birth' => '1990-01-01',
    ]);

    return ReferenceRequest::create([
        'token' => $token ?? Str::random(32),
        'caregiver_id' => $caregiver->id,
        'reference_name' => 'Jane Reference',
        'reference_email' => 'jane@example.com',
        'relationship' => 'Former Employer',
        'years_known' => '3-5',
        'is_sponsor' => false,
    ]);
}

function referencePortalValidPayload(array $overrides = []): array
{
    return array_merge([
        'relationship' => 'Former Supervisor',
        'years_known' => '5-10',
        'rating_reliability' => 5,
        'rating_trustworthiness' => 5,
        'rating_maturity' => 4,
        'rating_communication' => 5,
        'rating_warmth' => 4,
        'rating_overall_recommendation' => 5,
        'strengths' => 'Very responsible and caring.',
        'concerns' => '',
        'additional_comments' => '',
        'rating_appearance' => 5,
        'rating_punctuality' => 4,
        'background_drug_alcohol' => 'No',
        'background_tobacco' => 'No',
        'trust_own_child' => 'Yes',
        'reason_not_care' => 'No',
        'reason_not_care_explanation' => '',
    ], $overrides);
}

describe('Reference Portal - Show', function () {
    it('shows the reference form for a valid token', function () {
        $reference = referencePortalCreateReferenceRequest();

        $response = $this->get("/references/{$reference->token}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('public/references/submit')
            ->where('referenceName', 'Jane Reference')
            ->where('token', $reference->token)
        );
    });

    it('shows submitted page for already completed reference', function () {
        $reference = referencePortalCreateReferenceRequest();
        $reference->update([
            'strengths' => 'Great person.',
            'submitted_at' => now(),
        ]);

        $response = $this->get("/references/{$reference->token}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('public/references/submitted')
        );
    });

    it('returns 404 for invalid token', function () {
        $response = $this->get('/references/invalid-token');

        $response->assertStatus(404);
    });
});

describe('Reference Portal - Submit', function () {
    it('submits reference successfully', function () {
        Mail::fake();

        $reference = referencePortalCreateReferenceRequest();

        $response = $this->post("/references/{$reference->token}", referencePortalValidPayload());

        $response->assertRedirect("/references/{$reference->token}");

        $reference->refresh();
        expect($reference->relationship)->toBe('Former Supervisor');
        expect($reference->years_known)->toBe('5-10');
        expect($reference->rating_reliability)->toBe(5);
        expect($reference->rating_trustworthiness)->toBe(5);
        expect($reference->strengths)->toBe('Very responsible and caring.');
        expect($reference->submitted_at)->not->toBeNull();
    });

    it('rejects duplicate submission', function () {
        $reference = referencePortalCreateReferenceRequest();
        $reference->update([
            'strengths' => 'Already submitted.',
            'submitted_at' => now(),
        ]);

        $response = $this->post("/references/{$reference->token}", referencePortalValidPayload([
            'relationship' => 'Friend',
        ]));

        $response->assertSessionHasErrors('token');
    });

    it('validates rating_reliability is required', function () {
        $reference = referencePortalCreateReferenceRequest();

        $response = $this->post("/references/{$reference->token}", referencePortalValidPayload([
            'rating_reliability' => '',
        ]));

        $response->assertSessionHasErrors('rating_reliability');
    });

    it('validates rating must be between 1 and 5', function () {
        $reference = referencePortalCreateReferenceRequest();

        $response = $this->post("/references/{$reference->token}", referencePortalValidPayload([
            'rating_reliability' => 6,
        ]));

        $response->assertSessionHasErrors('rating_reliability');
    });

    it('validates strengths is required', function () {
        $reference = referencePortalCreateReferenceRequest();

        $response = $this->post("/references/{$reference->token}", referencePortalValidPayload([
            'strengths' => '',
        ]));

        $response->assertSessionHasErrors('strengths');
    });

    it('validates relationship is required', function () {
        $reference = referencePortalCreateReferenceRequest();

        $response = $this->post("/references/{$reference->token}", referencePortalValidPayload([
            'relationship' => '',
        ]));

        $response->assertSessionHasErrors('relationship');
    });

    it('validates years_known must be valid option', function () {
        $reference = referencePortalCreateReferenceRequest();

        $response = $this->post("/references/{$reference->token}", referencePortalValidPayload([
            'years_known' => 'forever',
        ]));

        $response->assertSessionHasErrors('years_known');
    });

    it('sends admin notification on successful submission', function () {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.test']);

        $reference = referencePortalCreateReferenceRequest();

        $this->post("/references/{$reference->token}", referencePortalValidPayload());

        Notification::assertSentTo(
            $admin,
            ReferenceCompletedNotification::class,
            function ($notification) use ($reference) {
                return $notification->referenceName === 'Jane Reference'
                    && $notification->applicantName === $reference->caregiver->first_name.' '.$reference->caregiver->last_name;
            }
        );
    });

    it('does not send notification when only super admin exists', function () {
        Notification::fake();
        User::factory()->create(['role' => 'super_admin', 'email' => 'super@example.test']);

        $reference = referencePortalCreateReferenceRequest();

        $this->post("/references/{$reference->token}", referencePortalValidPayload([
            'rating_overall_recommendation' => 4,
            'strengths' => 'Good caregiver.',
        ]));

        Notification::assertNothingSent();
    });

    it('validates background_drug_alcohol is required', function () {
        $reference = referencePortalCreateReferenceRequest();

        $response = $this->post("/references/{$reference->token}", referencePortalValidPayload([
            'background_drug_alcohol' => '',
        ]));

        $response->assertSessionHasErrors('background_drug_alcohol');
    });

    it('returns 404 for invalid token on store', function () {
        $response = $this->post('/references/invalid-token', referencePortalValidPayload());

        $response->assertStatus(404);
    });
});

describe('Reference Portal - ReferenceRequest Model', function () {
    it('scopes pending references correctly', function () {
        $pending = referencePortalCreateReferenceRequest();
        $completed = referencePortalCreateReferenceRequest();
        $completed->update([
            'strengths' => 'Done!',
            'submitted_at' => now(),
        ]);

        expect(ReferenceRequest::pending()->count())->toBe(1);
        expect(ReferenceRequest::completed()->count())->toBe(1);
    });

    it('belongs to a caregiver', function () {
        $reference = referencePortalCreateReferenceRequest();

        expect($reference->caregiver)->toBeInstanceOf(Caregiver::class);
    });
});
