<?php

namespace App\Console\Commands;

use App\Enums\CaregiverStatus;
use App\Mail\ApplicantPendingReferencesMail;
use App\Mail\ReferenceFinalReminderMail;
use App\Mail\ReferenceReminderMail;
use App\Models\Caregiver;
use App\Models\ReferenceRequest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

#[Signature('app:nudge-pending-references {--reference-side : Nudge reference contacts with pending forms} {--applicant-side : Nudge applicants with pending references}')]
#[Description('Send reminders for incomplete reference requests')]
class NudgePendingReferences extends Command
{
    public function handle()
    {
        $runReference = $this->option('reference-side') || (! $this->option('reference-side') && ! $this->option('applicant-side'));
        $runApplicant = $this->option('applicant-side') || (! $this->option('reference-side') && ! $this->option('applicant-side'));

        $referenceSent = 0;
        $applicantPrompted = 0;
        $applicantStalled = 0;

        if ($runReference) {
            // Day 2 first reminder: pending references aged 2-5 days
            $firstReminderCandidates = ReferenceRequest::pending()
                ->where('created_at', '<', now()->subDays(2))
                ->where('created_at', '>=', now()->subDays(5))
                ->get();

            foreach ($firstReminderCandidates as $reference) {
                Mail::to($reference->reference_email)->queue(
                    new ReferenceReminderMail(
                        $reference->reference_name,
                        $reference->caregiver->first_name.' '.$reference->caregiver->last_name,
                        $reference->token,
                    ),
                );
                $referenceSent++;
            }

            // Day 5 final reminder: pending references aged 5+ days
            $finalReminderCandidates = ReferenceRequest::pending()
                ->where('created_at', '<', now()->subDays(5))
                ->get();

            foreach ($finalReminderCandidates as $reference) {
                Mail::to($reference->reference_email)->queue(
                    new ReferenceFinalReminderMail(
                        $reference->reference_name,
                        $reference->caregiver->first_name.' '.$reference->caregiver->last_name,
                        $reference->token,
                    ),
                );
                $referenceSent++;
            }
        }

        if ($runApplicant) {
            // Group pending references by caregiver
            $caregiverIds = ReferenceRequest::pending()
                ->pluck('caregiver_id')
                ->unique();

            foreach ($caregiverIds as $caregiverId) {
                $caregiver = Caregiver::find($caregiverId);
                if (! $caregiver || ! $caregiver->user) {
                    continue;
                }

                $application = $caregiver->application;
                if (! $application || ! $application->submitted_at) {
                    continue;
                }

                $daysSinceSubmission = $application->submitted_at->diffInDays(now());

                if ($daysSinceSubmission >= 14) {
                    $caregiver->update(['status' => CaregiverStatus::Inactive]);
                    $applicantStalled++;
                } elseif ($daysSinceSubmission >= 7) {
                    Mail::to($caregiver->user->email)->queue(
                        new ApplicantPendingReferencesMail(
                            $caregiver->first_name.' '.$caregiver->last_name,
                            $daysSinceSubmission,
                        ),
                    );
                    $applicantPrompted++;
                } elseif ($daysSinceSubmission >= 3) {
                    Mail::to($caregiver->user->email)->queue(
                        new ApplicantPendingReferencesMail(
                            $caregiver->first_name.' '.$caregiver->last_name,
                            $daysSinceSubmission,
                        ),
                    );
                    $applicantPrompted++;
                }
            }
        }

        $this->line("Reference reminders sent: {$referenceSent}");
        $this->line("Applicant prompts sent: {$applicantPrompted}");
        $this->line("Applicants stalled (status → Inactive): {$applicantStalled}");
    }
}
