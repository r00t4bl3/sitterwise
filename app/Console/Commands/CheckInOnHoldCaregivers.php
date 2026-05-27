<?php

namespace App\Console\Commands;

use App\Mail\CaregiverOnHoldCheckinMail;
use App\Models\CaregiverPause;
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
        $activePauses = CaregiverPause::active()
            ->where('paused_at', '<=', now()->subDays(30))
            ->get();

        $sent = 0;

        foreach ($activePauses as $pause) {
            $daysOnHold = $pause->paused_at->diffInDays(now());

            $tier = match (true) {
                $daysOnHold >= 60 => 'final',
                $daysOnHold >= 45 => 'reminder',
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
