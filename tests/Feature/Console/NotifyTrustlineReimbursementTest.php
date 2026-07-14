<?php

use App\Mail\TrustlineReimbursementEarnedMail;
use App\Models\Booking;
use App\Models\Caregiver;
use App\Models\CaregiverApplication;
use App\Models\CertificationType;
use App\Models\TrustlineReimbursement;
use App\Support\Settings;
use Database\Seeders\AttributeDefinitionSeeder;
use Database\Seeders\CertificationTypeSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\SpecialtyTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    $this->seed([
        SettingsSeeder::class,
        CertificationTypeSeeder::class,
        SpecialtyTypeSeeder::class,
        LocationSeeder::class,
        AttributeDefinitionSeeder::class,
    ]);
    // Keep the positive-path fixtures cheap: earn at 2 completed jobs.
    Settings::set('trustline.jobs_threshold', 2);
});

/**
 * Build a caregiver with the requested eligibility ingredients.
 */
function trustlineCandidate(
    int $completedJobs = 2,
    bool $withApplication = true,
    bool $certified = true,
    bool $verified = true,
): Caregiver {
    $caregiver = Caregiver::factory()->create();

    // The factory attaches random certifications; clear them for a deterministic
    // Trustline state.
    $caregiver->certifications()->detach();

    if ($withApplication) {
        CaregiverApplication::factory()->create(['caregiver_id' => $caregiver->id]);
    }

    if ($certified) {
        $trustlineId = CertificationType::where('name', 'Trustline')->value('id');
        $caregiver->certifications()->attach($trustlineId, [
            'verified_at' => $verified ? now() : null,
        ]);
    }

    Booking::factory()->count($completedJobs)->create([
        'caregiver_id' => $caregiver->id,
        'status' => 'completed',
    ]);

    return $caregiver;
}

describe('caregivers:notify-trustline-reimbursement', function () {
    test('notifies the team and records the reimbursement for an eligible caregiver', function () {
        $caregiver = trustlineCandidate(completedJobs: 2);

        $this->artisan('caregivers:notify-trustline-reimbursement')
            ->expectsOutputToContain('Trustline reimbursements notified: 1');

        Mail::assertSent(TrustlineReimbursementEarnedMail::class, function ($mail) use ($caregiver) {
            return $mail->caregiver->is($caregiver)
                && $mail->hasTo(config('mail.team_bcc') ?? config('mail.from.address'));
        });

        $record = TrustlineReimbursement::where('caregiver_id', $caregiver->id)->first();
        expect($record)->not->toBeNull()
            ->and($record->jobs_completed)->toBe(2)
            ->and($record->reward_amount)->toBe(140)
            ->and($record->notified_at)->not->toBeNull()
            ->and($record->paid_at)->toBeNull();
    });

    test('is idempotent — a second run sends nothing and creates no duplicate', function () {
        $caregiver = trustlineCandidate();

        $this->artisan('caregivers:notify-trustline-reimbursement');
        Mail::assertSent(TrustlineReimbursementEarnedMail::class, 1);

        $this->artisan('caregivers:notify-trustline-reimbursement')
            ->expectsOutputToContain('Trustline reimbursements notified: 0');

        Mail::assertSent(TrustlineReimbursementEarnedMail::class, 1);
        expect(TrustlineReimbursement::where('caregiver_id', $caregiver->id)->count())->toBe(1);
    });

    test('a caregiver without an application is not notified', function () {
        trustlineCandidate(withApplication: false);

        $this->artisan('caregivers:notify-trustline-reimbursement')
            ->expectsOutputToContain('Trustline reimbursements notified: 0');

        Mail::assertNothingSent();
        expect(TrustlineReimbursement::count())->toBe(0);
    });

    test('a caregiver without the Trustline certification is not notified', function () {
        trustlineCandidate(certified: false);

        $this->artisan('caregivers:notify-trustline-reimbursement')
            ->expectsOutputToContain('Trustline reimbursements notified: 0');

        Mail::assertNothingSent();
    });

    test('an attached-but-unverified Trustline certification does not qualify', function () {
        trustlineCandidate(verified: false);

        $this->artisan('caregivers:notify-trustline-reimbursement')
            ->expectsOutputToContain('Trustline reimbursements notified: 0');

        Mail::assertNothingSent();
    });

    test('a caregiver below the threshold is not notified', function () {
        trustlineCandidate(completedJobs: 1); // threshold is 2

        $this->artisan('caregivers:notify-trustline-reimbursement')
            ->expectsOutputToContain('Trustline reimbursements notified: 0');

        Mail::assertNothingSent();
    });

    test('the threshold is driven by the setting', function () {
        Settings::set('trustline.jobs_threshold', 3);
        trustlineCandidate(completedJobs: 2); // would qualify at 2, not at 3

        $this->artisan('caregivers:notify-trustline-reimbursement')
            ->expectsOutputToContain('Trustline reimbursements notified: 0');

        Mail::assertNothingSent();
    });
});
