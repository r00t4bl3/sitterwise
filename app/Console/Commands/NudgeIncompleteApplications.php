<?php

namespace App\Console\Commands;

use App\Mail\ApplicantFinalReminderMail;
use App\Mail\ApplicantResumeApplicationMail;
use App\Models\IncompleteApplication;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

#[Signature('app:nudge-incomplete-applications')]
#[Description('Send reminder emails to applicants who started but did not submit their application')]
class NudgeIncompleteApplications extends Command
{
    public function handle()
    {
        // 48-hour nudge: send resume email to first-time nudges
        $resumeCandidates = IncompleteApplication::needsNudge()
            ->where('nudge_count', 0)
            ->get();

        foreach ($resumeCandidates as $incomplete) {
            Mail::to($incomplete->email)->queue(
                new ApplicantResumeApplicationMail($incomplete->email),
            );

            $incomplete->update([
                'nudged_at' => now(),
                'nudge_count' => 1,
            ]);

            $this->info("Resume nudge sent to: {$incomplete->email}");
        }

        // 7-day nudge: send final reminder to second-time nudges
        $finalCandidates = IncompleteApplication::whereNull('archived_at')
            ->where('nudge_count', 1)
            ->where('last_activity_at', '<', now()->subDays(7))
            ->get();

        foreach ($finalCandidates as $incomplete) {
            Mail::to($incomplete->email)->queue(
                new ApplicantFinalReminderMail($incomplete->email),
            );

            $incomplete->update([
                'nudged_at' => now(),
                'nudge_count' => 2,
            ]);

            $this->info("Final reminder sent to: {$incomplete->email}");
        }

        $this->info('Done. Sent '.($resumeCandidates->count() + $finalCandidates->count()).' nudges.');
    }
}
