<?php

use App\Enums\CaregiverStatus;
use App\Mail\ApplicantDeclinedMail;
use App\Models\Caregiver;
use App\Models\CaregiverApplication;
use App\Models\OnboardingChecklistItem;
use App\Models\User;
use Database\Seeders\AttributeDefinitionSeeder;
use Database\Seeders\CertificationTypeSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\SpecialtyTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        SpecialtyTypeSeeder::class,
        LocationSeeder::class,
        AttributeDefinitionSeeder::class,
        CertificationTypeSeeder::class,
    ]);

    $this->admin = User::factory()->create(['role' => 'admin']);

    $user = User::factory()->create(['role' => 'caregiver', 'email' => 'applicant@example.com']);
    $this->caregiver = Caregiver::factory()->create([
        'user_id' => $user->id,
        'status' => CaregiverStatus::Applicant,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
    ]);

    $this->application = CaregiverApplication::create([
        'caregiver_id' => $this->caregiver->id,
        'data' => ['personal' => ['first_name' => 'Jane', 'last_name' => 'Doe']],
        'submitted_at' => now(),
    ]);
});

function statusRoute(string $action, CaregiverApplication $application): string
{
    return "/applications/{$application->id}/{$action}";
}

describe('Application lifecycle - forward transitions', function () {
    it('approve moves Applicant to Under Review', function () {
        actingAs($this->admin)
            ->post(statusRoute('approve', $this->application))
            ->assertSessionHas('success');

        $this->caregiver->refresh();
        expect($this->caregiver->status)->toBe(CaregiverStatus::UnderReview);
    });

    it('schedule-interview moves Under Review to Interview Scheduled', function () {
        $this->caregiver->update(['status' => CaregiverStatus::UnderReview]);

        actingAs($this->admin)
            ->post(statusRoute('schedule-interview', $this->application))
            ->assertSessionHas('success');

        $this->caregiver->refresh();
        expect($this->caregiver->status)->toBe(CaregiverStatus::InterviewScheduled);
    });

    it('background-check moves Interview Scheduled to Background Check', function () {
        $this->caregiver->update(['status' => CaregiverStatus::InterviewScheduled]);

        actingAs($this->admin)
            ->post(statusRoute('background-check', $this->application))
            ->assertSessionHas('success');

        $this->caregiver->refresh();
        expect($this->caregiver->status)->toBe(CaregiverStatus::BackgroundCheck);
    });

    it('hire moves Background Check to Hired Onboarding', function () {
        $this->caregiver->update(['status' => CaregiverStatus::BackgroundCheck]);

        actingAs($this->admin)
            ->post(statusRoute('hire', $this->application))
            ->assertSessionHas('success');

        $this->caregiver->refresh();
        expect($this->caregiver->status)->toBe(CaregiverStatus::HiredOnboarding);
    });

    it('hire seeds onboarding checklist items', function () {
        $this->caregiver->update(['status' => CaregiverStatus::BackgroundCheck]);

        actingAs($this->admin)
            ->post(statusRoute('hire', $this->application))
            ->assertSessionHas('success');

        expect($this->caregiver->onboardingChecklistItems()->count())->toBe(6);
    });

    it('approve rejects non-Applicant status', function () {
        $this->caregiver->update(['status' => CaregiverStatus::UnderReview]);

        actingAs($this->admin)
            ->post(statusRoute('approve', $this->application))
            ->assertStatus(422);
    });

    it('schedule-interview rejects non-UnderReview status', function () {
        actingAs($this->admin)
            ->post(statusRoute('schedule-interview', $this->application))
            ->assertStatus(422);
    });

    it('background-check rejects non-InterviewScheduled status', function () {
        actingAs($this->admin)
            ->post(statusRoute('background-check', $this->application))
            ->assertStatus(422);
    });

    it('hire rejects non-BackgroundCheck status', function () {
        actingAs($this->admin)
            ->post(statusRoute('hire', $this->application))
            ->assertStatus(422);
    });
});

describe('Application lifecycle - complete onboarding', function () {
    it('complete-onboarding moves Hired Onboarding to Active', function () {
        $this->caregiver->update(['status' => CaregiverStatus::HiredOnboarding]);
        OnboardingChecklistItem::seedForCaregiver($this->caregiver);

        // Complete all 6 items
        $this->caregiver->onboardingChecklistItems()->update(['completed_at' => now()]);

        actingAs($this->admin)
            ->post(statusRoute('complete-onboarding', $this->application))
            ->assertSessionHas('success');

        $this->caregiver->refresh();
        expect($this->caregiver->status)->toBe(CaregiverStatus::Active);
    });

    it('complete-onboarding rejects when items are pending', function () {
        $this->caregiver->update(['status' => CaregiverStatus::HiredOnboarding]);
        OnboardingChecklistItem::seedForCaregiver($this->caregiver);

        actingAs($this->admin)
            ->post(statusRoute('complete-onboarding', $this->application))
            ->assertSessionHas('error');
    });

    it('complete-onboarding rejects non-HiredOnboarding status', function () {
        actingAs($this->admin)
            ->post(statusRoute('complete-onboarding', $this->application))
            ->assertStatus(422);
    });
});

describe('Application lifecycle - decline', function () {
    it('declines Applicant to Inactive', function () {
        actingAs($this->admin)
            ->post(statusRoute('decline', $this->application))
            ->assertSessionHas('success');

        $this->caregiver->refresh();
        expect($this->caregiver->status)->toBe(CaregiverStatus::Inactive);
    });

    it('declines Under Review to Inactive', function () {
        $this->caregiver->update(['status' => CaregiverStatus::UnderReview]);

        actingAs($this->admin)
            ->post(statusRoute('decline', $this->application))
            ->assertSessionHas('success');

        $this->caregiver->refresh();
        expect($this->caregiver->status)->toBe(CaregiverStatus::Inactive);
    });

    it('decline sends email to applicant with note', function () {
        Mail::fake();

        actingAs($this->admin)
            ->post(statusRoute('decline', $this->application), [
                'note' => 'Not enough experience',
            ])
            ->assertSessionHas('success');

        Mail::assertQueued(ApplicantDeclinedMail::class, function ($mail) {
            return $mail->applicantName === 'Jane Doe'
                && $mail->reason === 'Not enough experience';
        });
    });

    it('cannot decline terminal statuses', function () {
        $terminal = [CaregiverStatus::Active, CaregiverStatus::Inactive];

        foreach ($terminal as $status) {
            $this->caregiver->update(['status' => $status]);

            actingAs($this->admin)
                ->post(statusRoute('decline', $this->application))
                ->assertStatus(422);
        }
    });

    it('requires admin role', function () {
        $nonAdmin = User::factory()->create(['role' => 'caregiver']);

        actingAs($nonAdmin)
            ->post(statusRoute('decline', $this->application))
            ->assertStatus(403);
    });
});

describe('Application list - status filter', function () {
    it('filters by status query parameter', function () {
        $this->caregiver->update(['status' => CaregiverStatus::UnderReview]);

        actingAs($this->admin)
            ->get('/applications?status=under_review')
            ->assertSuccessful()
            ->assertSee('Jane');
    });

    it('shows all applications without filter', function () {
        actingAs($this->admin)
            ->get('/applications')
            ->assertSuccessful()
            ->assertSee('Jane');
    });
});
