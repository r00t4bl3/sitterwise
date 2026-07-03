<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:reconcile-stale-hours {--apply : Persist changes (default is a dry run)}')]
#[Description('Recompute worked hours and money totals for completed jobs left at 0 hours by the import. Idempotent; charges nothing.')]
class ReconcileStaleHours extends Command
{
    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $bookings = Booking::query()
            ->whereIn('status', ['completed', 'paid'])
            ->whereNotNull('checkout_at')
            ->whereNotIn('payment_status', ['charged', 'succeeded', 'paid'])
            ->where(function ($q) {
                $q->whereNull('total_working_hour')->orWhere('total_working_hour', '<=', 0);
            })
            ->with('bookingGroup')
            ->orderBy('id')
            ->get();

        $this->info(($apply ? 'APPLYING' : 'DRY RUN').' — '.$bookings->count().' booking(s) matched.');

        $rows = [];
        $skipped = [];
        $saved = 0;

        foreach ($bookings as $booking) {
            if (! $booking->start_datetime || ! $booking->end_datetime || $booking->end_datetime->lte($booking->start_datetime)) {
                $skipped[] = [$booking->id, (string) $booking->start_datetime, (string) $booking->end_datetime];

                continue;
            }

            $before = number_format((float) $booking->total_service_amount, 2);

            $booking->total_working_hour = $booking->start_datetime->diffInMinutes($booking->end_datetime) / 60;
            $booking->calculateHourlyRate();
            $booking->calculateTotalAmount();

            $after = number_format((float) $booking->total_service_amount, 2);

            if ($apply) {
                $booking->save();
                $saved++;
            }

            $rows[] = [
                $booking->id,
                $booking->bookingGroup?->service_type,
                (string) $booking->total_working_hour,
                '$'.$before,
                '$'.$after,
            ];
        }

        if ($rows) {
            $this->table(['ID', 'Service', 'Hours', 'Before', 'After'], $rows);
        }

        if ($skipped) {
            $this->warn('Skipped — end not after start (manual review needed):');
            $this->table(['ID', 'Start', 'End'], $skipped);
        }

        $this->info($apply
            ? "Saved {$saved} booking(s). Nothing was charged — approve payments in the admin Transactions view."
            : 'Dry run only — re-run with --apply to persist.');

        return self::SUCCESS;
    }
}
