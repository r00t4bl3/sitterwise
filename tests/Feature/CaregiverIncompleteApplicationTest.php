<?php

use App\Mail\FinalReminderMail;
use App\Mail\ResumeApplicationMail;
use App\Models\IncompleteApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

use function Pest\Laravel\artisan;

// Helper is defined in CaregiverApplicationTest.php in the global namespace

uses(RefreshDatabase::class);

describe('IncompleteApplication model scopes', function () {
    it('needsNudge includes records inactive 48h+ that were never nudged', function () {
        IncompleteApplication::create([
            'email' => 'never-nudged@example.com',
            'last_activity_at' => now()->subHours(72),
            'nudge_count' => 0,
        ]);

        $results = IncompleteApplication::needsNudge()->get();

        expect($results)->toHaveCount(1);
    });

    it('needsNudge includes records inactive 48h+ nudged more than 1 day ago', function () {
        IncompleteApplication::create([
            'email' => 'stale-nudge@example.com',
            'last_activity_at' => now()->subHours(72),
            'nudged_at' => now()->subHours(36),
            'nudge_count' => 1,
        ]);

        $results = IncompleteApplication::needsNudge()->get();

        expect($results)->toHaveCount(1);
    });

    it('needsNudge excludes records nudged within the last day', function () {
        IncompleteApplication::create([
            'email' => 'recent-nudge@example.com',
            'last_activity_at' => now()->subHours(72),
            'nudged_at' => now()->subHours(6),
            'nudge_count' => 1,
        ]);

        $results = IncompleteApplication::needsNudge()->get();

        expect($results)->toHaveCount(0);
    });

    it('needsNudge excludes archived records', function () {
        IncompleteApplication::create([
            'email' => 'archived@example.com',
            'last_activity_at' => now()->subHours(72),
            'archived_at' => now(),
            'nudge_count' => 0,
        ]);

        $results = IncompleteApplication::needsNudge()->get();

        expect($results)->toHaveCount(0);
    });

    it('needsNudge excludes records active within last 48h', function () {
        IncompleteApplication::create([
            'email' => 'active@example.com',
            'last_activity_at' => now()->subHours(2),
            'nudge_count' => 0,
        ]);

        $results = IncompleteApplication::needsNudge()->get();

        expect($results)->toHaveCount(0);
    });

    it('stale scope includes records inactive 14d+', function () {
        IncompleteApplication::create([
            'email' => 'old@example.com',
            'last_activity_at' => now()->subDays(15),
        ]);

        $results = IncompleteApplication::stale()->get();

        expect($results)->toHaveCount(1);
    });

    it('stale scope excludes records active within 14 days', function () {
        IncompleteApplication::create([
            'email' => 'recent@example.com',
            'last_activity_at' => now()->subDays(5),
        ]);

        $results = IncompleteApplication::stale()->get();

        expect($results)->toHaveCount(0);
    });

    it('stale scope excludes already archived records', function () {
        IncompleteApplication::create([
            'email' => 'already-archived@example.com',
            'last_activity_at' => now()->subDays(15),
            'archived_at' => now()->subDay(),
        ]);

        $results = IncompleteApplication::stale()->get();

        expect($results)->toHaveCount(0);
    });

    it('expired scope includes records inactive 90d+', function () {
        IncompleteApplication::create([
            'email' => 'expired@example.com',
            'last_activity_at' => now()->subDays(91),
        ]);

        $results = IncompleteApplication::expired()->get();

        expect($results)->toHaveCount(1);
    });

    it('expired scope excludes records active within 90 days', function () {
        IncompleteApplication::create([
            'email' => 'recent@example.com',
            'last_activity_at' => now()->subDays(30),
        ]);

        $results = IncompleteApplication::expired()->get();

        expect($results)->toHaveCount(0);
    });
});

describe('NudgeIncompleteApplications command', function () {
    it('sends resume email to 48h-old un-nudged records', function () {
        Mail::fake();

        IncompleteApplication::create([
            'email' => 'nudge-me@example.com',
            'last_activity_at' => now()->subHours(72),
            'nudge_count' => 0,
        ]);

        artisan('app:nudge-incomplete-applications')->assertSuccessful();

        Mail::assertQueued(ResumeApplicationMail::class, function ($mail) {
            return $mail->email === 'nudge-me@example.com';
        });
    });

    it('increments nudge_count after sending resume email', function () {
        Mail::fake();

        $record = IncompleteApplication::create([
            'email' => 'count-me@example.com',
            'last_activity_at' => now()->subHours(72),
            'nudge_count' => 0,
        ]);

        artisan('app:nudge-incomplete-applications')->assertSuccessful();

        $record->refresh();
        expect($record->nudge_count)->toBe(1);
        expect($record->nudged_at)->not->toBeNull();
    });

    it('sends final reminder to 7d-old records with nudge_count 1', function () {
        Mail::fake();

        IncompleteApplication::create([
            'email' => 'final@example.com',
            'last_activity_at' => now()->subDays(8),
            'nudged_at' => now()->subDays(4),
            'nudge_count' => 1,
        ]);

        artisan('app:nudge-incomplete-applications')->assertSuccessful();

        Mail::assertQueued(FinalReminderMail::class, function ($mail) {
            return $mail->email === 'final@example.com';
        });
    });

    it('increments nudge_count after sending final reminder', function () {
        Mail::fake();

        $record = IncompleteApplication::create([
            'email' => 'final-count@example.com',
            'last_activity_at' => now()->subDays(8),
            'nudged_at' => now()->subDays(4),
            'nudge_count' => 1,
        ]);

        artisan('app:nudge-incomplete-applications')->assertSuccessful();

        $record->refresh();
        expect($record->nudge_count)->toBe(2);
    });

    it('does not send nudges to archived records', function () {
        Mail::fake();

        IncompleteApplication::create([
            'email' => 'archived@example.com',
            'last_activity_at' => now()->subHours(72),
            'archived_at' => now(),
            'nudge_count' => 0,
        ]);

        artisan('app:nudge-incomplete-applications')->assertSuccessful();

        Mail::assertNothingQueued();
    });

    it('does not send nudges to records with nudge_count >= 2', function () {
        Mail::fake();

        IncompleteApplication::create([
            'email' => 'maxed@example.com',
            'last_activity_at' => now()->subDays(10),
            'nudged_at' => now()->subDays(2),
            'nudge_count' => 2,
        ]);

        artisan('app:nudge-incomplete-applications')->assertSuccessful();

        Mail::assertNothingQueued();
    });

    it('does not send final reminder before 7 days', function () {
        Mail::fake();

        IncompleteApplication::create([
            'email' => 'too-early@example.com',
            'last_activity_at' => now()->subHours(72),
            'nudged_at' => now()->subHours(24),
            'nudge_count' => 1,
        ]);

        artisan('app:nudge-incomplete-applications')->assertSuccessful();

        Mail::assertNothingQueued();
    });

    it('handles both nudge types in a single run', function () {
        Mail::fake();

        IncompleteApplication::create([
            'email' => 'resume-me@example.com',
            'last_activity_at' => now()->subHours(72),
            'nudge_count' => 0,
        ]);

        IncompleteApplication::create([
            'email' => 'final-me@example.com',
            'last_activity_at' => now()->subDays(8),
            'nudged_at' => now()->subDays(4),
            'nudge_count' => 1,
        ]);

        artisan('app:nudge-incomplete-applications')->assertSuccessful();

        Mail::assertQueued(ResumeApplicationMail::class, 1);
        Mail::assertQueued(FinalReminderMail::class, 1);
    });
});

describe('ArchiveStalledApplications command', function () {
    it('archives records inactive for 14+ days', function () {
        $record = IncompleteApplication::create([
            'email' => 'stale@example.com',
            'last_activity_at' => now()->subDays(15),
        ]);

        artisan('app:archive-stalled-applications')->assertSuccessful();

        $record->refresh();
        expect($record->archived_at)->not->toBeNull();
    });

    it('does not archive records active within 14 days', function () {
        $record = IncompleteApplication::create([
            'email' => 'fresh@example.com',
            'last_activity_at' => now()->subDays(5),
        ]);

        artisan('app:archive-stalled-applications')->assertSuccessful();

        $record->refresh();
        expect($record->archived_at)->toBeNull();
    });

    it('deletes records inactive for 90+ days', function () {
        IncompleteApplication::create([
            'email' => 'expired@example.com',
            'last_activity_at' => now()->subDays(91),
        ]);

        artisan('app:archive-stalled-applications')->assertSuccessful();

        expect(IncompleteApplication::count())->toBe(0);
    });

    it('does not delete records active within 90 days', function () {
        IncompleteApplication::create([
            'email' => 'recent@example.com',
            'last_activity_at' => now()->subDays(30),
        ]);

        artisan('app:archive-stalled-applications')->assertSuccessful();

        expect(IncompleteApplication::count())->toBe(1);
    });
});

describe('Save progress clears application tracking on submit', function () {
    it('creates IncompleteApplication on saveProgress', function () {
        $this->withSession([
            'verified_email' => 'progress@example.com',
            'verified_at' => now(),
        ]);

        $this->post('/caregiver/apply/save-progress', [
            'step' => 3,
            'data' => ['personal' => ['first_name' => 'Jane']],
        ])->assertOk();

        expect(IncompleteApplication::count())->toBe(1);
        expect(IncompleteApplication::first()->email)->toBe('progress@example.com');
        expect(IncompleteApplication::first()->last_step)->toBe(3);
    });

    it('updates existing IncompleteApplication on subsequent saveProgress', function () {
        $this->withSession([
            'verified_email' => 'update@example.com',
            'verified_at' => now(),
        ]);

        $this->post('/caregiver/apply/save-progress', [
            'step' => 2,
            'data' => ['personal' => ['first_name' => 'John']],
        ]);

        $this->post('/caregiver/apply/save-progress', [
            'step' => 5,
            'data' => ['personal' => ['first_name' => 'John'], 'references' => []],
        ]);

        expect(IncompleteApplication::count())->toBe(1);
        expect(IncompleteApplication::first()->last_step)->toBe(5);
    });

    it('deletes IncompleteApplication on successful submission', function () {
        Mail::fake();

        $this->withSession([
            'verified_email' => 'complete@example.com',
            'verified_at' => now(),
        ]);

        $this->post('/caregiver/apply/save-progress', [
            'step' => 7,
            'data' => ['personal' => ['first_name' => 'John']],
        ]);

        expect(IncompleteApplication::count())->toBe(1);

        $data = caregiverApplicationGetValidApplicationData('complete@example.com');

        $this->post('/caregiver/apply/submit', $data);

        expect(IncompleteApplication::where('email', 'complete@example.com')->count())->toBe(0);
    });
});
