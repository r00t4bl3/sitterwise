<?php

namespace App\Console\Commands;

use App\Enums\AssignmentResolution;
use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('bookings:reset-stale-completed-assignments {--apply : Actually update records}')]
#[Description("Clear the stale 'completed' assignment resolution left by the Bubble import on still-active confirmed bookings so caregivers can check out")]
class ResetStaleCompletedAssignments extends Command
{
    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $bookings = Booking::query()
            ->where('status', BookingStatus::Confirmed->value)
            ->whereNull('checkout_at')
            ->whereNull('cancelled_at')
            ->whereHas('assignments', function ($query) {
                $query->whereColumn('caregiver_assignments.caregiver_id', 'bookings.caregiver_id')
                    ->where('resolution', AssignmentResolution::Completed->value);
            })
            ->with(['caregiver.user', 'assignments'])
            ->get();

        if ($bookings->isEmpty()) {
            $this->info('No stale completed assignments found.');

            return Command::SUCCESS;
        }

        $report = [];
        $changed = 0;

        foreach ($bookings as $booking) {
            $assignment = $booking->assignments
                ->firstWhere('caregiver_id', $booking->caregiver_id);

            if (! $assignment) {
                continue;
            }

            if ($apply) {
                $assignment->update([
                    'resolution' => null,
                    'resolution_at' => null,
                ]);
            }

            $report[] = [
                $booking->id,
                $booking->caregiver?->user?->name ?? "caregiver #{$booking->caregiver_id}",
                (string) $booking->start_datetime,
                'completed → null',
            ];
            $changed++;
        }

        $this->table(
            ['booking_id', 'caregiver', 'start_datetime', 'resolution'],
            $report
        );

        $this->newLine();
        $this->line("Stale completed assignments: {$changed}");

        if (! $apply) {
            $this->warn('Dry-run mode — use --apply to persist changes.');
        }

        return Command::SUCCESS;
    }
}
