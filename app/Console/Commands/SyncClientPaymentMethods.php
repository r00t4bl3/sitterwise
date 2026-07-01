<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Services\ClientPayment\ClientPaymentService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('payments:sync-client-methods {--client= : Specific client ID to sync payment methods for}')]
#[Description('Sync client payment methods from Stripe. Optionally specify a single client ID for verification.')]
class SyncClientPaymentMethods extends Command
{
    public function handle(ClientPaymentService $paymentService): int
    {
        $clientId = $this->option('client');

        if ($clientId) {
            $client = Client::find($clientId);

            if (! $client) {
                $this->error("Client with ID {$clientId} not found.");

                return 1;
            }

            if (! $client->stripe_customer_id) {
                $this->warn("Client {$clientId} has no Stripe customer ID. Skipping.");

                return 0;
            }

            $methods = $paymentService->syncPaymentMethodsFromStripe($client);

            $this->info("Synced {$client->full_name} (ID: {$clientId}): ".count($methods).' payment method(s).');

            return 0;
        }

        $query = Client::whereNotNull('stripe_customer_id')
            ->where('stripe_customer_id', '!=', '');

        $total = $query->count();
        $synced = 0;
        $errors = 0;

        $this->info("Syncing payment methods for {$total} client(s)...");

        foreach ($query->lazy(100) as $client) {
            try {
                $paymentService->syncPaymentMethodsFromStripe($client);
                $synced++;
            } catch (\Exception $e) {
                $this->error("Failed to sync client {$client->id}: {$e->getMessage()}");
                $errors++;
            }
        }

        $this->info("Done. Synced: {$synced}, Errors: {$errors}");

        return 0;
    }
}
