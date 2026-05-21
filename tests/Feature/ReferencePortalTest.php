<?php

use App\Mail\ReferenceCompletedMail;
use App\Models\Caregiver;
use App\Models\CaregiverStatus;
use App\Models\ReferenceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    CaregiverStatus::create([
        'name' => 'applicant',
        'color' => '#F48A91',
        'is_active' => true,
        'sort_order' => 1,
    ]);
});

function referencePortalCreateReferenceRequest(?string $token = null): ReferenceRequest
{
    $status = CaregiverStatus::where('name', 'applicant')->first();

    $user = User::factory()->create(['role' => 'caregiver']);
    $caregiver = Caregiver::create([
        'user_id' => $user->id,
        'status_id' => $status->id,
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
            'rating' => 5,
            'feedback' => 'Great caregiver!',
            'submitted_at' => now(),
        ]);

        $response = $this->get("/references/{$reference->token}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('public/references/submitted')
        );
    });

    it('returns 404 for invalid token', function () {
        $response = $this->get('/references/invalid-token-123');

        $response->assertStatus(404);
    });

    it('pre-fills relationship and years_known from application data', function () {
        $reference = referencePortalCreateReferenceRequest();

        $response = $this->get("/references/{$reference->token}");

        $response->assertInertia(fn ($page) => $page
            ->component('public/references/submit')
            ->where('defaults.relationship', 'Former Employer')
            ->where('defaults.years_known', '3-5')
        );
    });
});

describe('Reference Portal - Submit', function () {
    it('submits reference successfully', function () {
        Mail::fake();

        $reference = referencePortalCreateReferenceRequest();

        $response = $this->post("/references/{$reference->token}", [
            'relationship' => 'Former Supervisor',
            'years_known' => '5-10',
            'rating' => 5,
            'feedback' => 'John is an excellent caregiver. Very responsible and caring.',
        ]);

        $response->assertRedirect("/references/{$reference->token}");

        $reference->refresh();
        expect($reference->relationship)->toBe('Former Supervisor');
        expect($reference->years_known)->toBe('5-10');
        expect($reference->rating)->toBe(5);
        expect($reference->feedback)->toBe('John is an excellent caregiver. Very responsible and caring.');
        expect($reference->submitted_at)->not->toBeNull();
    });

    it('rejects duplicate submission', function () {
        $reference = referencePortalCreateReferenceRequest();
        $reference->update([
            'rating' => 4,
            'feedback' => 'Already submitted.',
            'submitted_at' => now(),
        ]);

        $response = $this->post("/references/{$reference->token}", [
            'relationship' => 'Friend',
            'years_known' => '3-5',
            'rating' => 5,
            'feedback' => 'Trying again.',
        ]);

        $response->assertSessionHasErrors('token');
    });

    it('validates rating is required', function () {
        $reference = referencePortalCreateReferenceRequest();

        $response = $this->post("/references/{$reference->token}", [
            'relationship' => 'Friend',
            'years_known' => '1-3',
            'feedback' => 'Good person.',
        ]);

        $response->assertSessionHasErrors('rating');
    });

    it('validates rating must be between 1 and 5', function () {
        $reference = referencePortalCreateReferenceRequest();

        $response = $this->post("/references/{$reference->token}", [
            'relationship' => 'Friend',
            'years_known' => '1-3',
            'rating' => 6,
            'feedback' => 'Good person.',
        ]);

        $response->assertSessionHasErrors('rating');
    });

    it('validates feedback is required', function () {
        $reference = referencePortalCreateReferenceRequest();

        $response = $this->post("/references/{$reference->token}", [
            'relationship' => 'Friend',
            'years_known' => '1-3',
            'rating' => 3,
        ]);

        $response->assertSessionHasErrors('feedback');
    });

    it('validates relationship is required', function () {
        $reference = referencePortalCreateReferenceRequest();

        $response = $this->post("/references/{$reference->token}", [
            'years_known' => '1-3',
            'rating' => 3,
            'feedback' => 'Good person.',
        ]);

        $response->assertSessionHasErrors('relationship');
    });

    it('validates years_known must be valid option', function () {
        $reference = referencePortalCreateReferenceRequest();

        $response = $this->post("/references/{$reference->token}", [
            'relationship' => 'Friend',
            'years_known' => 'forever',
            'rating' => 3,
            'feedback' => 'Good person.',
        ]);

        $response->assertSessionHasErrors('years_known');
    });

    it('sends admin notification on successful submission', function () {
        Mail::fake();
        User::factory()->create(['role' => 'admin', 'email' => 'admin@example.test']);

        $reference = referencePortalCreateReferenceRequest();

        $this->post("/references/{$reference->token}", [
            'relationship' => 'Former Employer',
            'years_known' => '3-5',
            'rating' => 5,
            'feedback' => 'Excellent caregiver!',
        ]);

        Mail::assertQueued(ReferenceCompletedMail::class, function ($mail) use ($reference) {
            return $mail->referenceName === 'Jane Reference'
                && $mail->applicantName === $reference->caregiver->first_name.' '.$reference->caregiver->last_name;
        });
    });

    it('sends admin notification to super admins too', function () {
        Mail::fake();
        User::factory()->create(['role' => 'super_admin', 'email' => 'super@example.test']);

        $reference = referencePortalCreateReferenceRequest();

        $this->post("/references/{$reference->token}", [
            'relationship' => 'Former Employer',
            'years_known' => '3-5',
            'rating' => 4,
            'feedback' => 'Good caregiver.',
        ]);

        Mail::assertQueued(ReferenceCompletedMail::class, 1);
    });

    it('returns 404 for invalid token on store', function () {
        $response = $this->post('/references/invalid-token', [
            'relationship' => 'Friend',
            'years_known' => '1-3',
            'rating' => 3,
            'feedback' => 'Good person.',
        ]);

        $response->assertStatus(404);
    });
});

describe('Reference Portal - ReferenceRequest Model', function () {
    it('scopes pending references correctly', function () {
        $pending = referencePortalCreateReferenceRequest();
        $completed = referencePortalCreateReferenceRequest();
        $completed->update([
            'rating' => 5,
            'feedback' => 'Done!',
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
