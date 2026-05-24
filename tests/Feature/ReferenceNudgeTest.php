<?php

use App\Enums\CaregiverStatus;
use App\Mail\ApplicantPendingReferencesMail;
use App\Mail\ReferenceFinalReminderMail;
use App\Mail\ReferenceReminderMail;
use App\Models\Caregiver;
use App\Models\CaregiverApplication;
use App\Models\ReferenceRequest;
use App\Models\User;
use Database\Seeders\AttributeDefinitionSeeder;
use Database\Seeders\CertificationTypeSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\SpecialtyTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        SpecialtyTypeSeeder::class,
        LocationSeeder::class,
        AttributeDefinitionSeeder::class,
        CertificationTypeSeeder::class,
    ]);
});

function createPendingReference(int $daysAgo, bool $withCaregiver = true): ReferenceRequest
{
    if ($withCaregiver) {
        $user = User::factory()->create(['role' => 'caregiver']);
        $caregiver = Caregiver::factory()->create([
            'user_id' => $user->id,
            'status' => CaregiverStatus::Applicant,
        ]);
    }

    $reference = new ReferenceRequest;
    $reference->forceFill([
        'caregiver_id' => $withCaregiver ? $caregiver->id : 1,
        'reference_name' => 'Jane Reference',
        'reference_email' => 'reference@example.com',
        'relationship' => 'Former Employer',
        'years_known' => '3-5',
        'is_sponsor' => false,
        'token' => (string) Str::uuid(),
        'created_at' => now()->copy()->subDays($daysAgo),
    ]);
    $reference->save();

    return $reference;
}

function createApplicantWithPendingReferences(int $daysSinceSubmission): Caregiver
{
    $user = User::factory()->create(['role' => 'caregiver', 'email' => 'applicant@example.com']);
    $caregiver = Caregiver::factory()->create([
        'user_id' => $user->id,
        'status' => CaregiverStatus::Applicant,
        'first_name' => 'Test',
        'last_name' => 'Applicant',
    ]);

    $app = new CaregiverApplication;
    $app->forceFill([
        'caregiver_id' => $caregiver->id,
        'data' => ['test' => true],
        'submitted_at' => now()->copy()->subDays($daysSinceSubmission),
    ]);
    $app->save();

    $reference = new ReferenceRequest;
    $reference->forceFill([
        'caregiver_id' => $caregiver->id,
        'reference_name' => 'Ref One',
        'reference_email' => 'ref1@example.com',
        'relationship' => 'Friend',
        'years_known' => '3-5',
        'is_sponsor' => false,
        'token' => (string) Str::uuid(),
        'created_at' => now(),
    ]);
    $reference->save();

    return $caregiver;
}

describe('Reference-side nudges', function () {
    it('sends first reminder to pending references aged 2-5 days', function () {
        Mail::fake();

        $reference = createPendingReference(daysAgo: 3);

        artisan('app:nudge-pending-references --reference-side')->assertSuccessful();

        Mail::assertQueued(ReferenceReminderMail::class, function ($mail) use ($reference) {
            return $mail->referenceName === $reference->reference_name
                && $mail->token === $reference->token;
        });
    });

    it('sends final reminder to pending references aged 5+ days', function () {
        Mail::fake();

        $reference = createPendingReference(daysAgo: 6);

        artisan('app:nudge-pending-references --reference-side')->assertSuccessful();

        Mail::assertQueued(ReferenceFinalReminderMail::class, function ($mail) use ($reference) {
            return $mail->referenceName === $reference->reference_name
                && $mail->token === $reference->token;
        });
    });

    it('does not send to completed references', function () {
        Mail::fake();

        $reference = createPendingReference(daysAgo: 3);
        $reference->update(['submitted_at' => now()]);

        artisan('app:nudge-pending-references --reference-side')->assertSuccessful();

        Mail::assertNothingQueued();
    });

    it('does not send to references less than 2 days old', function () {
        Mail::fake();

        createPendingReference(daysAgo: 1);

        artisan('app:nudge-pending-references --reference-side')->assertSuccessful();

        Mail::assertNothingQueued();
    });

    it('does nothing with only --applicant-side flag', function () {
        Mail::fake();

        createPendingReference(daysAgo: 3);

        artisan('app:nudge-pending-references --applicant-side')->assertSuccessful();

        Mail::assertNothingQueued();
    });
});

describe('Applicant-side nudges', function () {
    it('sends prompt to applicant with pending references at day 3', function () {
        Mail::fake();

        createApplicantWithPendingReferences(daysSinceSubmission: 3);

        artisan('app:nudge-pending-references --applicant-side')->assertSuccessful();

        Mail::assertQueued(ApplicantPendingReferencesMail::class, function ($mail) {
            return $mail->applicantName === 'Test Applicant'
                && $mail->daysSinceSubmission === 3;
        });
    });

    it('sends prompt to applicant with pending references at day 7', function () {
        Mail::fake();

        createApplicantWithPendingReferences(daysSinceSubmission: 7);

        artisan('app:nudge-pending-references --applicant-side')->assertSuccessful();

        Mail::assertQueued(ApplicantPendingReferencesMail::class, function ($mail) {
            return $mail->applicantName === 'Test Applicant'
                && $mail->daysSinceSubmission === 7;
        });
    });

    it('changes status to Inactive at day 14', function () {
        Mail::fake();

        $caregiver = createApplicantWithPendingReferences(daysSinceSubmission: 14);

        artisan('app:nudge-pending-references --applicant-side')->assertSuccessful();

        $caregiver->refresh();
        expect($caregiver->status)->toBe(CaregiverStatus::Inactive);
    });

    it('does not send prompt to applicant within first 3 days', function () {
        Mail::fake();

        createApplicantWithPendingReferences(daysSinceSubmission: 2);

        artisan('app:nudge-pending-references --applicant-side')->assertSuccessful();

        Mail::assertNothingQueued();
    });

    it('does not prompt when all references are completed', function () {
        Mail::fake();

        $caregiver = createApplicantWithPendingReferences(daysSinceSubmission: 5);
        $caregiver->referenceRequests()->update(['submitted_at' => now()]);

        artisan('app:nudge-pending-references --applicant-side')->assertSuccessful();

        Mail::assertNothingQueued();
    });

    it('does nothing with only --reference-side flag', function () {
        Mail::fake();

        createApplicantWithPendingReferences(daysSinceSubmission: 5);

        artisan('app:nudge-pending-references --reference-side')->assertSuccessful();

        Mail::assertNothingQueued();
    });
});

describe('Combined mode', function () {
    it('runs both sides when no option is provided', function () {
        Mail::fake();

        createPendingReference(daysAgo: 3);
        createApplicantWithPendingReferences(daysSinceSubmission: 5);

        artisan('app:nudge-pending-references')->assertSuccessful();

        Mail::assertQueued(ReferenceReminderMail::class, 1);
        Mail::assertQueued(ApplicantPendingReferencesMail::class, 1);
    });
});
