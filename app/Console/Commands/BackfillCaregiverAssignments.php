<?php

namespace App\Console\Commands;

use App\Enums\AssignmentResolution;
use App\Models\Booking;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:backfill-assignments')]
#[Description('Create caregiver_assignments records for existing bookings')]
class BackfillCaregiverAssignments extends Command
{
    public function handle(): int
    {
        $bookings = Booking::whereNotNull('caregiver_id')
            ->with('caregiver')
            ->get();

        $count = 0;
        $bar = $this->output->createProgressBar($bookings->count());

        foreach ($bookings as $booking) {
            $resolution = match ($booking->status) {
                'cancelled' => AssignmentResolution::CancelledBySitterwise,
                default => AssignmentResolution::Completed,
            };

            $booking->assignments()->firstOrCreate(
                ['caregiver_id' => $booking->caregiver_id],
                [
                    'assigned_at' => $booking->confirmed_at ?? $booking->created_at,
                    'resolution' => $resolution->value,
                    'resolution_at' => $resolution === AssignmentResolution::Completed
                        ? ($booking->end_datetime ?? $booking->updated_at)
                        : $booking->updated_at,
                ],
            );

            $count++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Backfilled {$count} assignments.");

        return Command::SUCCESS;
    }
}
