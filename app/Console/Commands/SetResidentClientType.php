<?php

namespace App\Console\Commands;

use App\Enums\ClientType;
use App\Models\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SetResidentClientType extends Command
{
    protected $signature = 'clients:set-resident-type
        {--dry-run : Preview changes without updating}
        {--csv= : Path to CSV file (defaults to database/seeders/data/resident_clients.csv)}';

    protected $description = 'Update client_type to resident for clients matching emails in the CSV';

    public function handle(): int
    {
        $csvPath = $this->option('csv') ?: database_path('seeders/data/resident_clients.csv');

        if (! file_exists($csvPath)) {
            $this->error("CSV file not found: {$csvPath}");

            return Command::FAILURE;
        }

        $emails = collect(file($csvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))
            ->map(fn (string $line): string => strtolower(trim($line)))
            ->unique()
            ->values();

        $this->info("Loaded {$emails->count()} unique email(s) from CSV.");

        $matchedUserEmails = DB::table('users')
            ->whereIn('email', $emails)
            ->pluck('email')
            ->map(fn (string $email): string => strtolower($email))
            ->values();

        $unmatchedEmails = $emails->diff($matchedUserEmails);

        $this->newLine();
        $this->info(sprintf(
            'Source CSV total: %d emails (%d matched + %d unmatched)',
            $emails->count(),
            $matchedUserEmails->count(),
            $unmatchedEmails->count(),
        ));

        if ($unmatchedEmails->isNotEmpty()) {
            $this->warn("{$unmatchedEmails->count()} email(s) from CSV did not match any user:");
            $this->table(
                ['Unmatched Emails'],
                $unmatchedEmails->map(fn (string $email): array => [$email])->toArray()
            );
        }

        $clients = Client::query()
            ->whereIn('user_id', function ($query) use ($matchedUserEmails) {
                $query->select('id')
                    ->from('users')
                    ->whereIn('email', $matchedUserEmails);
            })
            ->get();

        $this->newLine();
        $this->info("Found {$clients->count()} client(s) matching those emails.");

        if ($clients->isEmpty()) {
            $this->warn('No matching clients found. Nothing to do.');

            return Command::SUCCESS;
        }

        $alreadyResident = $clients->where('client_type', ClientType::Resident->value);
        $toUpdate = $clients->where('client_type', '!==', ClientType::Resident->value);

        $this->newLine();
        $this->table(
            ['', 'Count'],
            [
                ['Already resident', $alreadyResident->count()],
                ['Will be updated', $toUpdate->count()],
                ['Total matched', $clients->count()],
            ]
        );

        if ($toUpdate->isEmpty()) {
            $this->info('All matching clients are already resident.');

            return Command::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn('Dry run — no changes were made.');

            return Command::SUCCESS;
        }

        $progress = $this->output->createProgressBar($toUpdate->count());
        $progress->start();

        $updatedCount = 0;

        foreach ($toUpdate as $client) {
            DB::transaction(function () use ($client, &$updatedCount) {
                $previousType = $client->client_type;

                $client->update([
                    'client_type' => ClientType::Resident->value,
                ]);

                $client->typeChanges()->create([
                    'changed_by_admin_id' => 1,
                    'previous_type' => $previousType ?: 'vacationer',
                    'new_type' => 'sd_resident',
                    'reason' => 'Bulk update from resident clients CSV',
                    'changed_at' => now(),
                ]);

                $updatedCount++;
            });

            $progress->advance();
        }

        $progress->finish();

        $this->newLine(2);
        $this->info("Updated {$updatedCount} client(s) to resident type.");

        return Command::SUCCESS;
    }
}
