<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Mail\TrustlineReimbursementEarnedMail;
use App\Models\Caregiver;
use App\Models\CertificationType;
use App\Support\Settings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

#[Signature('caregivers:notify-trustline-reimbursement')]
#[Description('Email the team when a Trustline-certified caregiver (with an application) reaches the completed-jobs threshold and has earned their reimbursement')]
class NotifyTrustlineReimbursement extends Command
{
    public function handle(): int
    {
        $threshold = (int) Settings::get('trustline.jobs_threshold', 10);
        $reward = (int) Settings::get('trustline.reward_amount', 140);

        $trustlineTypeId = CertificationType::where('name', 'Trustline')->value('id');

        if (! $trustlineTypeId) {
            $this->info('Trustline certification type not found.');

            return Command::SUCCESS;
        }

        $caregivers = Caregiver::query()
            ->whereDoesntHave('trustlineReimbursement')
            ->whereHas('application')
            ->whereHas('certifications', function ($query) use ($trustlineTypeId) {
                $query->where('certification_type_id', $trustlineTypeId)
                    ->whereNotNull('caregiver_certifications.verified_at');
            })
            ->whereHas('bookings', function ($query) {
                $query->whereIn('status', [BookingStatus::Completed->value, BookingStatus::Paid->value]);
            }, '>=', $threshold)
            ->withCount(['bookings as completed_jobs_count' => function ($query) {
                $query->whereIn('status', [BookingStatus::Completed->value, BookingStatus::Paid->value]);
            }])
            ->with('user')
            ->get();

        $notified = 0;

        foreach ($caregivers as $caregiver) {
            $caregiver->trustlineReimbursement()->create([
                'jobs_completed' => $caregiver->completed_jobs_count,
                'reward_amount' => $reward,
                'notified_at' => now(),
            ]);

            Mail::to(config('mail.team_bcc') ?? config('mail.from.address'))
                ->send(new TrustlineReimbursementEarnedMail($caregiver, (int) $caregiver->completed_jobs_count, $reward));

            $notified++;
        }

        $this->info("Trustline reimbursements notified: {$notified}");

        return Command::SUCCESS;
    }
}
