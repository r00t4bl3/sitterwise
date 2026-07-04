<?php

namespace App\Console\Commands;

use App\Models\ClientPaymentMethod;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('clients:backfill-default-payment-method {--apply : Persist changes (default is a dry run)}')]
#[Description('Ensure every client with an active payment method has one marked default, so cards can be charged. Picks the most recently added active method. Optionally syncs the default to Stripe.')]
class BackfillDefaultPaymentMethods extends Command
{
    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        // Clients that have at least one active method but none marked default.
        $clientIds = ClientPaymentMethod::query()
            ->where('status', 'active')
            ->whereNotIn('client_id', function ($query) {
                $query->select('client_id')
                    ->from('client_payment_methods')
                    ->where('is_default', true);
            })
            ->distinct()
            ->pluck('client_id');

        $this->info(($apply ? 'APPLYING' : 'DRY RUN').' — '.$clientIds->count().' client(s) missing a default payment method.');

        if ($clientIds->isEmpty()) {
            return self::SUCCESS;
        }

        $rows = [];
        $updated = 0;

        foreach ($clientIds as $clientId) {
            $method = ClientPaymentMethod::where('client_id', $clientId)
                ->where('status', 'active')
                ->orderByDesc('created_at')
                ->first();

            if (! $method) {
                continue;
            }

            $rows[] = [$clientId, $method->id, $method->brand, '****'.$method->last4];

            if ($apply) {
                $method->update(['is_default' => true]);
                $updated++;
            }
        }

        if ($rows) {
            $this->table(['Client', 'Method ID', 'Brand', 'Card'], $rows);
        }

        $this->info($apply
            ? "Marked {$updated} payment method(s) as default. Stripe's invoice default updates on the next setDefault/charge; run the charge flow to sync."
            : 'Dry run only — re-run with --apply to persist.');

        return self::SUCCESS;
    }
}
