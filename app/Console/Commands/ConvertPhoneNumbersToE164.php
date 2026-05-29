<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ConvertPhoneNumbersToE164 extends Command
{
    protected $signature = 'app:convert-phone-numbers-to-e164
        {--dry-run : Preview changes without updating}';

    protected $description = 'Convert existing phone numbers to E.164 format (+1XXXXXXXXXX)';

    public function handle(): int
    {
        $tables = [
            ['table' => 'clients', 'column' => 'phone', 'label' => 'Clients'],
            ['table' => 'bookings', 'column' => 'client_phone', 'label' => 'Bookings'],
            ['table' => 'caregivers', 'column' => 'phone', 'label' => 'Caregivers'],
            ['table' => 'hotels', 'column' => 'contact_phone', 'label' => 'Hotels'],
        ];

        $dryRun = $this->option('dry-run');
        $totalConverted = 0;

        foreach ($tables as $t) {
            $rows = DB::table($t['table'])
                ->whereNotNull($t['column'])
                ->where($t['column'], '!=', '')
                ->get(['id', $t['column']]);

            if ($rows->isEmpty()) {
                $this->info("[{$t['label']}] No phone numbers to convert.");

                continue;
            }

            $this->newLine();
            $this->info("[{$t['label']}] Processing {$rows->count()} row(s)...");

            $converted = 0;
            $progress = $this->output->createProgressBar($rows->count());
            $progress->start();

            foreach ($rows as $row) {
                $digits = preg_replace('/[^0-9]/', '', $row->{$t['column']});

                if (strlen($digits) === 0) {
                    $progress->advance();

                    continue;
                }

                $e164 = match (true) {
                    strlen($digits) === 10 => '+1'.$digits,
                    strlen($digits) === 11 && str_starts_with($digits, '1') => '+'.$digits,
                    default => '+'.$digits,
                };

                if ($e164 === $row->{$t['column']}) {
                    $progress->advance();

                    continue;
                }

                if (! $dryRun) {
                    DB::table($t['table'])->where('id', $row->id)->update([$t['column'] => $e164]);
                }

                $converted++;
                $progress->advance();
            }

            $progress->finish();
            $this->newLine(2);
            $this->line("<info>[{$t['label']}]</info> Converted {$converted} of {$rows->count()} row(s).");
            $totalConverted += $converted;
        }

        $this->newLine();

        if ($dryRun) {
            $this->warn("Dry run — no changes were made. {$totalConverted} phone(s) would be converted.");

            return Command::SUCCESS;
        }

        $this->info("Done. Converted {$totalConverted} phone number(s) to E.164 format.");

        return Command::SUCCESS;
    }
}
