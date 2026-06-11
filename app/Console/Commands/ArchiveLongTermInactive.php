<?php

namespace App\Console\Commands;

use App\Enums\CaregiverStatus;
use App\Mail\CaregiverArchiveWarningMail;
use App\Models\CaregiverPause;
use App\Models\User;
use App\Notifications\AdminCaregiverArchivedNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

#[Signature('app:archive-long-term-inactive')]
#[Description('Warn caregivers at 166 days and archive to inactive at 180 days on hold')]
class ArchiveLongTermInactive extends Command
{
    public function handle()
    {
        // 14-day warning: 166-179 days on hold
        $warningThreshold = now()->subDays(166);
        $archiveThreshold = now()->subDays(180);

        $warningCandidates = CaregiverPause::active()
            ->where('paused_at', '<=', $warningThreshold)
            ->where('paused_at', '>', $archiveThreshold)
            ->get();

        $warned = 0;

        foreach ($warningCandidates as $pause) {
            $caregiver = $pause->caregiver;

            Mail::to($caregiver->user->email)->queue(
                new CaregiverArchiveWarningMail(
                    caregiverName: $caregiver->first_name,
                    daysOnHold: $pause->paused_at->diffInDays(now()),
                ),
            );

            $this->info("Archive warning sent to {$caregiver->user->email}");
            $warned++;
        }

        // Archive: 180+ days on hold → set to Inactive
        $archiveCandidates = CaregiverPause::active()
            ->where('paused_at', '<=', $archiveThreshold)
            ->get();

        $archived = 0;

        foreach ($archiveCandidates as $pause) {
            $caregiver = $pause->caregiver;
            $caregiver->update(['status' => CaregiverStatus::Inactive]);
            $pause->update(['resumed_at' => now()]);

            $admins = User::where('role', 'admin')->get();
            Notification::send($admins, new AdminCaregiverArchivedNotification(
                caregiverName: "{$caregiver->first_name} {$caregiver->last_name}",
                caregiverId: $caregiver->id,
                daysOnHold: $pause->paused_at->diffInDays(now()),
            ));

            $this->info("Archived caregiver {$caregiver->id} ({$caregiver->first_name} {$caregiver->last_name}) to Inactive");
            $archived++;
        }

        $this->info("Done. Sent {$warned} warnings, archived {$archived} caregivers.");
    }
}
