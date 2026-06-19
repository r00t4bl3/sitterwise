<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('app:clear-dummy-phones {--dry-run : Preview changes without updating}')]
#[Description('Clear dummy/test phone numbers from caregivers and clients')]
class ClearDummyPhones extends Command
{
    protected array $tables = [
        ['table' => 'caregivers', 'column' => 'phone', 'label' => 'Caregivers'],
        ['table' => 'clients', 'column' => 'phone', 'label' => 'Clients'],
    ];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $totalCleared = 0;

        foreach ($this->tables as $t) {
            $rows = DB::table($t['table'])
                ->whereNotNull($t['column'])
                ->where($t['column'], '!=', '')
                ->get(['id', $t['column']]);

            if ($rows->isEmpty()) {
                $this->info("[{$t['label']}] No phone numbers to check.");

                continue;
            }

            $dummy = $rows->filter(fn ($row) => $this->isDummyPhone($row->{$t['column']}));

            if ($dummy->isEmpty()) {
                $this->info("[{$t['label']}] No dummy phone numbers found.");

                continue;
            }

            $this->newLine();
            $this->info("[{$t['label']}] Found {$dummy->count()} dummy phone number(s):");

            foreach ($dummy as $row) {
                $this->line("  ID {$row->id}: {$row->{$t['column']}}");

                if (! $dryRun) {
                    DB::table($t['table'])->where('id', $row->id)->update([$t['column'] => null]);
                }
            }

            $totalCleared += $dummy->count();
        }

        $this->newLine();

        if ($dryRun) {
            $this->warn("Dry run — no changes were made. {$totalCleared} phone(s) would be cleared.");

            return Command::SUCCESS;
        }

        $this->info("Done. Cleared {$totalCleared} dummy phone number(s).");

        return Command::SUCCESS;
    }

    protected function isDummyPhone(string $phone): bool
    {
        $digits = preg_replace('/[^0-9]/', '', $phone);

        if (strlen($digits) === 0) {
            return false;
        }

        $knownDummy = [
            '+19999999999', '+15555555555', '+10000000000',
            '+00000000000', '+000000000000', '+19000000001',
        ];

        if (in_array($phone, $knownDummy, true)) {
            return true;
        }

        // Numbers where all digits are the same (e.g., 1111111111, 0000000000)
        if (strlen($digits) >= 7 && count(array_unique(str_split($digits))) === 1) {
            return true;
        }

        return false;
    }
}
