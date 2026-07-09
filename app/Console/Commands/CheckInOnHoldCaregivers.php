<?php

namespace App\Console\Commands;

use App\Mail\CaregiverOnHoldCheckinMail;
use App\Models\CaregiverPause;
use App\Support\Settings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

#[Signature('app:check-in-on-hold-caregivers')]
#[Description('Send check-in emails to caregivers on hold at 30, 45, and 60 day thresholds')]
class CheckInOnHoldCaregivers extends Command
{
    public function handle()
    {
        $startDays = (int) Settings::get('caregiver.checkin_start_days', 30);
        $reminderDays = (int) Settings::get('caregiver.checkin_reminder_days', 45);
        $finalDays = (int) Settings::get('caregiver.checkin_final_days', 60);

        $activePauses = CaregiverPause::active()
            ->where('paused_at', '<=', now()->subDays($startDays))
            ->get();

        $sent = 0;

        foreach ($activePauses as $pause) {
            $daysOnHold = $pause->paused_at->diffInDays(now());

            $tier = match (true) {
                $daysOnHold >= $finalDays => 'final',
                $daysOnHold >= $reminderDays => 'reminder',
                default => 'checkin',
            };

            $caregiver = $pause->caregiver;

            Mail::to($caregiver->user->email)->queue(
                new CaregiverOnHoldCheckinMail(
                    caregiverName: $caregiver->first_name,
                    daysOnHold: $daysOnHold,
                    tier: $tier,
                ),
            );

            $this->info("{$tier} email sent to {$caregiver->user->email} ({$daysOnHold} days on hold)");
            $sent++;
        }

        $this->info("Done. Sent {$sent} check-in emails.");
    }
}
