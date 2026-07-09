<?php

namespace App\Console\Commands;

use App\Models\CaregiverAssignment;
use App\Support\Settings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[Signature('app:check-late-arrivals')]
#[Description('Flag caregivers with 3+ late arrivals in the past 60 days for admin review')]
class CheckLateArrivals extends Command
{
    public function handle()
    {
        $count = (int) Settings::get('caregiver.late_arrival_count', 3);
        $windowDays = (int) Settings::get('caregiver.late_arrival_window_days', 60);

        $flagged = CaregiverAssignment::query()
            ->selectRaw('caregiver_id, COUNT(*) as late_count')
            ->where('late_arrival_flag', true)
            ->where('created_at', '>=', now()->subDays($windowDays))
            ->groupBy('caregiver_id')
            ->having('late_count', '>=', $count)
            ->get();

        if ($flagged->isEmpty()) {
            $this->info("No caregivers with {$count}+ late arrivals in the past {$windowDays} days.");

            return;
        }

        foreach ($flagged as $record) {
            $message = "Caregiver #{$record->caregiver_id} has {$record->late_count} late arrivals in the past {$windowDays} days.";
            $this->warn($message);
            Log::warning($message);
        }

        $this->info("Flagged {$flagged->count()} caregiver(s) for late arrival review.");
    }
}
