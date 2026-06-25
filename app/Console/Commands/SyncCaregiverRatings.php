<?php

namespace App\Console\Commands;

use App\Models\Caregiver;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:sync-caregiver-ratings')]
#[Description('Recalculate the cached rating for all caregivers from their booking_ratings, excluding soft-deleted ratings.')]
class SyncCaregiverRatings extends Command
{
    public function handle(): int
    {
        $count = Caregiver::query()
            ->lazy(100)
            ->each(fn (Caregiver $caregiver) => $caregiver->recalculateRating())
            ->count();

        $this->info("Ratings synced for {$count} caregiver(s).");

        return 0;
    }
}
