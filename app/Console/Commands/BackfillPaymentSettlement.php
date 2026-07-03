<?php

namespace App\Console\Commands;

use App\Models\BookingGroup;
use App\Models\PricingRule;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:backfill-payment-settlement {--apply : Persist changes (default is a dry run)}')]
#[Description('Recompute requires_payment + payment_form on booking_groups from the pricing table. Idempotent; changes no money.')]
class BackfillPaymentSettlement extends Command
{
    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        // Precompute the expected values per service type once — there are only
        // a handful — instead of querying the pricing table for every group.
        $requiresByType = [];
        $formByType = [];
        foreach (PricingRule::query()->get(['service_type', 'charge_to_client', 'payment_form'])->groupBy('service_type') as $type => $rules) {
            $requiresByType[$type] = (float) $rules->max('charge_to_client') > 0;
            $formByType[$type] = $rules->first()->payment_form;
        }

        $rows = [];
        $totalChanged = 0;

        foreach (BookingGroup::query()->distinct()->pluck('service_type') as $type) {
            // Unknown service types (no pricing rule) default to billable, null rail.
            $expectedRequires = $type === null ? true : ($requiresByType[$type] ?? true);
            $expectedForm = $type === null ? null : ($formByType[$type] ?? null);

            $base = BookingGroup::query()->when(
                $type === null,
                fn ($q) => $q->whereNull('service_type'),
                fn ($q) => $q->where('service_type', $type),
            );

            $mismatch = (clone $base)->where(function ($q) use ($expectedRequires, $expectedForm) {
                $q->where('requires_payment', '!=', $expectedRequires);

                if ($expectedForm === null) {
                    $q->orWhereNotNull('payment_form');
                } else {
                    $q->orWhere('payment_form', '!=', $expectedForm)->orWhereNull('payment_form');
                }
            })->count();

            if ($mismatch === 0) {
                continue;
            }

            $rows[] = [$type ?? '(none)', $expectedForm ?? 'null', $expectedRequires ? '1' : '0', $mismatch];

            if ($apply) {
                (clone $base)->update([
                    'requires_payment' => $expectedRequires,
                    'payment_form' => $expectedForm,
                ]);
                $totalChanged += $mismatch;
            }
        }

        $this->info(($apply ? 'APPLYING' : 'DRY RUN').' — groups needing a change, by service type:');
        $this->table(['Service type', 'payment_form →', 'requires_payment →', 'Groups'], $rows ?: [['—', '—', '—', 0]]);

        $this->info($apply
            ? "Updated {$totalChanged} group(s). Re-run to confirm 0 remain."
            : 'Dry run only — re-run with --apply to persist.');

        return self::SUCCESS;
    }
}
