<?php

namespace App\Console\Commands;

use App\Models\ClientPayment;
use App\Services\ImportUserService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('clients:normalize-payment-amounts {--apply : Actually update records}')]
#[Description('Normalize client_payment amounts from cents to dollars by cross-referencing Bubble staging data')]
class NormalizePaymentAmounts extends Command
{
    protected ?\PDO $sqlite = null;

    public function handle(): int
    {
        $apply = $this->option('apply');

        if (! $this->initStagingDatabase()) {
            return Command::SUCCESS;
        }

        $payments = ClientPayment::whereNotNull('bubble_id')
            ->orderBy('bubble_id')
            ->get();

        if ($payments->isEmpty()) {
            $this->warn('No client_payments with bubble_id found.');

            return Command::SUCCESS;
        }

        $this->line("Processing {$payments->count()} client_payments...\n");

        $report = [];
        $changed = 0;
        $notFound = 0;

        foreach ($payments as $payment) {
            $rawAmount = $this->getRawAmount($payment->bubble_id);

            if ($rawAmount === null) {
                $report[] = [
                    $payment->bubble_id,
                    number_format($payment->amount, 2),
                    'NOT FOUND',
                    '-',
                    '-',
                    'SKIP',
                ];
                $notFound++;

                continue;
            }

            $type = gettype($rawAmount);
            $newAmount = ImportUserService::normalizeAmount($rawAmount);
            $isChanged = abs((float) $payment->amount - $newAmount) > 0.001;

            if ($isChanged && $apply) {
                $payment->update(['amount' => $newAmount]);
            }

            $report[] = [
                $payment->bubble_id,
                number_format($payment->amount, 2),
                is_float($rawAmount) ? number_format($rawAmount, 2) : (string) $rawAmount,
                $type,
                number_format($newAmount, 2),
                $isChanged ? 'YES' : 'NO',
            ];

            if ($isChanged) {
                $changed++;
            }
        }

        $this->table(
            ['bubble_id', 'old_amount', 'raw_value', 'type', 'new_amount', 'changed'],
            $report
        );

        $this->newLine();
        $this->line("Total: {$payments->count()}, Changed: {$changed}, Not found in staging: {$notFound}");

        if ($changed > 0 && ! $apply) {
            $this->warn('Dry-run mode — use --apply to persist changes.');
        }

        return Command::SUCCESS;
    }

    protected function getRawAmount(string $bubbleId): float|int|null
    {
        $stmt = $this->sqlite->prepare(
            'SELECT json_extract(raw_json, \'$.amount_number\') FROM staged_records WHERE external_id = ? AND type = ?'
        );
        $stmt->execute([$bubbleId, 'transactions']);
        $value = $stmt->fetchColumn();

        if ($value === false || $value === null) {
            return null;
        }

        return is_numeric($value) ? ($value + 0) : null;
    }

    /**
     * A missing staging database is reported as a graceful no-op rather than
     * via exit(): calling exit() from a command terminates the whole PHP
     * process, which kills the test runner (and any queue worker or scheduler
     * invoking the command in-process) mid-run.
     */
    protected function initStagingDatabase(): bool
    {
        $dbPath = storage_path('app/bubble_staging.sqlite');
        if (! file_exists($dbPath)) {
            $this->warn("Staging database not found at {$dbPath}; nothing to normalize.");

            return false;
        }
        $this->sqlite = new \PDO("sqlite:{$dbPath}");
        $this->sqlite->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        return true;
    }
}
