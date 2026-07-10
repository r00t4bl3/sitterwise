<?php

namespace App\Console\Commands;

use App\Enums\CaregiverStatus;
use App\Mail\CaregiverArchiveWarningMail;
use App\Models\CaregiverPause;
use App\Models\User;
use App\Notifications\AdminCaregiverArchivedNotification;
use App\Support\Settings;
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
        // Warning window: [archive_warning_days, archive_days) on hold.
        $warningThreshold = now()->subDays((int) Settings::get('caregiver.archive_warning_days', 166));
        $archiveThreshold = now()->subDays((int) Settings::get('caregiver.archive_days', 180));

        $warningCandidates = CaregiverPause::active()
            ->where('paused_at', '<=', $warningThreshold)
            ->where('paused_at', '>', $archiveThreshold)
            ->get();

        $warned = 0;

        foreach ($warningCandidates as $pause) {
            $caregiver = $pause->caregiver;

            // A caregiver reactivated outside the resume flow (e.g. an admin
            // status change) can be left with a dangling active pause. The
            // archive pipeline is "N days ON HOLD", so never warn/archive someone
            // no longer on hold — just close out the stale pause.
            if ($caregiver->status !== CaregiverStatus::OnHold) {
                $pause->update(['resumed_at' => now()]);

                continue;
            }

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

            // Only archive caregivers who are genuinely still on hold. One who was
            // reactivated (admin status change) without resolving their pause must
            // not be flipped back to Inactive — close the stale pause and skip.
            if ($caregiver->status !== CaregiverStatus::OnHold) {
                $pause->update(['resumed_at' => now()]);

                continue;
            }

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
