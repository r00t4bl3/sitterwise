<?php

namespace App\Console\Commands;

use App\Models\Caregiver;
use App\Models\CertificationType;
use Illuminate\Console\Command;

class ImportTrustlineCsv extends Command
{
    protected $signature = 'caregivers:import-trustline
        {--dry-run : Preview changes without updating}
        {--csv= : Path to CSV file (defaults to database/seeders/data/trustline.csv)}';

    protected $description = 'Import trustline certifications from CSV by matching caregiver names';

    public function handle(): int
    {
        $csvPath = $this->option('csv') ?: database_path('seeders/data/trustline.csv');

        if (! file_exists($csvPath)) {
            $this->error("CSV file not found: {$csvPath}");

            return Command::FAILURE;
        }

        $trustlineType = CertificationType::where('name', 'Trustline')->first();

        if (! $trustlineType) {
            $this->error('Trustline certification type not found. Did you run the seeder?');

            return Command::FAILURE;
        }

        $lines = file($csvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        array_shift($lines); // skip header row

        $rows = collect($lines)
            ->map(fn (string $line): ?array => str_getcsv($line, escape: '\\'))
            ->filter(fn (?array $cols): bool => ! empty($cols[1]))
            ->values();

        $totalRows = $rows->count();
        $this->info("Loaded {$totalRows} caregiver name(s) from CSV.");

        $matched = [];
        $unmatched = [];

        foreach ($rows as $cols) {
            $fullName = trim($cols[1]);
            $caregiver = $this->matchCaregiver($fullName);

            if ($caregiver) {
                $matched[] = $caregiver;
            } else {
                $unmatched[] = $fullName;
            }
        }

        $this->newLine();
        $this->info(sprintf(
            'Source CSV total: %d names (%d matched + %d unmatched)',
            $totalRows,
            count($matched),
            count($unmatched),
        ));

        if ($unmatched !== []) {
            $this->warn(sprintf('%d name(s) from CSV did not match any caregiver:', count($unmatched)));
            $this->table(
                ['Unmatched Names'],
                array_map(fn (string $name): array => [$name], $unmatched),
            );
        }

        if ($matched === []) {
            $this->warn('No matching caregivers found. Nothing to do.');

            return Command::SUCCESS;
        }

        $alreadyTrustline = 0;
        $toCreate = [];

        foreach ($matched as $caregiver) {
            $hasTrustline = $caregiver->certifications()
                ->where('certification_type_id', $trustlineType->id)
                ->exists();

            if ($hasTrustline) {
                $alreadyTrustline++;
            } else {
                $toCreate[] = $caregiver;
            }
        }

        $this->newLine();
        $this->table(
            ['', 'Count'],
            [
                ['Already have trustline', $alreadyTrustline],
                ['Will be created', count($toCreate)],
                ['Total matched', count($matched)],
            ],
        );

        if ($toCreate === []) {
            $this->info('All matching caregivers already have trustline certification.');

            return Command::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn('Dry run — no changes were made.');

            return Command::SUCCESS;
        }

        $progress = $this->output->createProgressBar(count($toCreate));
        $progress->start();

        $createdCount = 0;

        foreach ($toCreate as $caregiver) {
            $caregiver->certifications()->syncWithoutDetaching([
                $trustlineType->id => [
                    'verified_at' => now(),
                ],
            ]);

            $createdCount++;
            $progress->advance();
        }

        $progress->finish();

        $this->newLine(2);
        $this->info("Created trustline certification for {$createdCount} caregiver(s).");

        return Command::SUCCESS;
    }

    private function matchCaregiver(string $fullName): ?Caregiver
    {
        $firstSpace = strpos($fullName, ' ');

        if ($firstSpace === false) {
            return Caregiver::where('first_name', $fullName)->first();
        }

        $first = substr($fullName, 0, $firstSpace);
        $last = substr($fullName, $firstSpace + 1);

        $caregiver = Caregiver::where('first_name', $first)
            ->where('last_name', $last)
            ->first();

        if ($caregiver) {
            return $caregiver;
        }

        // Pass 2: split on last space — handles nicknames in first_name
        // e.g. "Susan (Suzi) Kufus" → first="Susan (Suzi)", last="Kufus"
        $lastSpace = strrpos($fullName, ' ');

        if ($lastSpace !== false && $lastSpace !== $firstSpace) {
            $first = substr($fullName, 0, $lastSpace);
            $last = substr($fullName, $lastSpace + 1);

            return Caregiver::where('first_name', $first)
                ->where('last_name', $last)
                ->first();
        }

        return null;
    }
}
