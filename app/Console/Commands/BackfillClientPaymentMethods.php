<?php

namespace App\Console\Commands;

use App\Models\ClientPayment;
use App\Models\ClientPaymentMethod;
use Illuminate\Console\Command;

class BackfillClientPaymentMethods extends Command
{
    protected $signature = 'clients:backfill-payment-methods';

    protected $description = 'Backfill payment_method_id for client_payments with null values';

    public function handle(): int
    {
        $clientIds = ClientPayment::whereNull('payment_method_id')
            ->whereNotNull('client_id')
            ->distinct()
            ->pluck('client_id');

        if ($clientIds->isEmpty()) {
            $this->info('No client payments with null payment_method_id found.');

            return Command::SUCCESS;
        }

        $this->info("Processing {$clientIds->count()} clients with null payment_method_id...\n");

        $bar = $this->output->createProgressBar($clientIds->count());

        $updated = 0;
        $skipped = 0;

        foreach ($clientIds as $clientId) {
            $method = ClientPaymentMethod::where('client_id', $clientId)
                ->orderByDesc('is_default')
                ->orderByDesc('created_at')
                ->first();

            if ($method) {
                $affected = ClientPayment::where('client_id', $clientId)
                    ->whereNull('payment_method_id')
                    ->update(['payment_method_id' => $method->id]);

                $updated += $affected;
            } else {
                $skipped++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Clients processed', $clientIds->count()],
                ['Clients updated', $clientIds->count() - $skipped],
                ['Clients skipped (no methods)', $skipped],
                ['Payments updated', $updated],
            ]
        );

        return Command::SUCCESS;
    }
}
