<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('caregivers:backfill-missing-assignments {--apply : Actually create records}')]
#[Description('Create the missing unresolved (Pending) caregiver_assignments row for still-active confirmed bookings whose current caregiver has none (e.g. accepted via a flow that bypassed the model hook), so checkout works and the job shows in assignment history')]
class BackfillMissingCaregiverAssignments extends Command
{
    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        // Only still-active confirmed bookings: their missing row hides the
        // checkout button and drops the ongoing job from assignment history. We
        // deliberately do NOT backfill completed/paid bookings — fabricating a
        // 'completed' resolution there would retroactively change reliability
        // scores and milestone counts, which are computed from assignment rows.
        $bookings = Booking::query()
            ->where('status', BookingStatus::Confirmed->value)
            ->whereNull('checkout_at')
            ->whereNull('cancelled_at')
            ->whereNotNull('caregiver_id')
            ->whereDoesntHave('assignments', function ($query) {
                $query->whereColumn('caregiver_assignments.caregiver_id', 'bookings.caregiver_id');
            })
            ->with('caregiver.user')
            ->get();

        if ($bookings->isEmpty()) {
            $this->info('No bookings need a backfilled assignment row.');

            return Command::SUCCESS;
        }

        $report = [];

        foreach ($bookings as $booking) {
            if ($apply) {
                $booking->assignments()->updateOrCreate(
                    ['caregiver_id' => $booking->caregiver_id],
                    [
                        'assigned_at' => $booking->start_datetime ?? now(),
                        'resolution' => null,
                        'resolution_at' => null,
                        'resolution_note' => null,
                    ],
                );
            }

            $report[] = [
                $booking->id,
                $booking->caregiver?->user?->name ?? "caregiver #{$booking->caregiver_id}",
                (string) $booking->start_datetime,
                'pending (null)',
            ];
        }

        $this->table(
            ['booking_id', 'caregiver', 'start_datetime', 'resolution'],
            $report
        );

        $this->newLine();
        $this->line('Missing assignment rows: '.count($report));

        if (! $apply) {
            $this->warn('Dry-run mode — use --apply to persist changes.');
        }

        return Command::SUCCESS;
    }
}
