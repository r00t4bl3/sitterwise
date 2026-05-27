<?php

namespace App\Console\Commands;

use App\Models\CaregiverAssignment;
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
        $flagged = CaregiverAssignment::query()
            ->selectRaw('caregiver_id, COUNT(*) as late_count')
            ->where('late_arrival_flag', true)
            ->where('created_at', '>=', now()->subDays(60))
            ->groupBy('caregiver_id')
            ->having('late_count', '>=', 3)
            ->get();

        if ($flagged->isEmpty()) {
            $this->info('No caregivers with 3+ late arrivals in the past 60 days.');

            return;
        }

        foreach ($flagged as $record) {
            $message = "Caregiver #{$record->caregiver_id} has {$record->late_count} late arrivals in the past 60 days.";
            $this->warn($message);
            Log::warning($message);
        }

        $this->info("Flagged {$flagged->count()} caregiver(s) for late arrival review.");
    }
}
