<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\ImportUserService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('import:staged {type : The Bubble data type to import (user, jobs, rating, transactions)} {--force : Overwrite existing records} {--dry-run : Preview without saving} {--limit= : Limit number of records}')]
#[Description('Import data from local staging database with pass-based bulk operations')]
class ImportStagedData extends Command
{
    protected ?\PDO $sqlite = null;

    public function handle(): int
    {
        $type = $this->argument('type');
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');
        $limit = $this->option('limit');

        $this->initStagingDatabase();

        $stagedCount = $this->getStagedCount($type);
        if ($stagedCount === 0) {
            $this->warn("No staged data found for [{$type}]. Run 'import:bubble {$type}' first to stage data.");

            return Command::SUCCESS;
        }

        $this->info("Loading staged records for [{$type}]...");
        $hits = $this->loadHits($type, $limit);

        if (empty($hits)) {
            $this->warn('No records loaded.');

            return Command::SUCCESS;
        }

        if ($type === 'user') {
            $hits = $this->sortHitsByRole($hits);
        }

        $total = count($hits);
        $this->info("Loaded $total records.");

        if ($dryRun) {
            $this->warn("Dry-run: would process {$total} records.");
            foreach ($hits as $i => $hit) {
                $source = $hit['source'];
                $email = $source['authentication']['email']['email'] ?? '';
                $names = ImportUserService::parseSourceNames($source, $email);
                $name = trim($names['first'].' '.$names['last']) ?: $hit['id'];
                $role = $source['role_permissions_option_role'] ?? 'caregiver';
                $this->line('  '.($i + 1).": $name ($role)");
            }

            return Command::SUCCESS;
        }

        if ($type === 'user') {
            $this->importUsers($hits, $force);
        } elseif ($type === 'jobs') {
            $this->importJobs($hits, $force);
        } elseif ($type === 'rating') {
            $this->importRatings($hits);
        } elseif ($type === 'transactions') {
            $this->importTransactions($hits);
        }

        return Command::SUCCESS;
    }

    protected function importUsers(array $hits, bool $force): void
    {
        $service = app(ImportUserService::class);
        $total = count($hits);
        $timer = microtime(true);

        // Pass 1 — Users
        $this->info("Pass 1/10: Users ($total records)...");
        $usersByBubbleId = $service->passUsers($hits, $force);
        $this->info('  '.count($usersByBubbleId).' users in database. ('.$this->elapsed($timer).')');

        // Pass 2 — Caregivers
        $timer = microtime(true);
        $this->info("Pass 2/10: Caregivers ($total records)...");
        $caregiverIds = $service->passCaregivers($hits, $usersByBubbleId, $force);
        $this->info('  '.count($caregiverIds).' caregivers processed. ('.$this->elapsed($timer).')');

        // Pass 3 — Clients
        $timer = microtime(true);
        $this->info("Pass 3/10: Clients ($total records)...");
        $clientIds = $service->passClients($hits, $usersByBubbleId, $force);
        $this->info('  '.count($clientIds).' clients processed. ('.$this->elapsed($timer).')');

        // Pass 4 — Caregiver Educations
        $timer = microtime(true);
        $this->info("Pass 4/10: Caregiver Educations ($total records)...");
        $service->passCaregiverEducations($hits, $usersByBubbleId);
        $this->info('  Done. ('.$this->elapsed($timer).')');

        // Pass 5 — Caregiver Experiences
        $timer = microtime(true);
        $this->info("Pass 5/10: Caregiver Experiences ($total records)...");
        $service->passCaregiverExperiences($hits, $usersByBubbleId);
        $this->info('  Done. ('.$this->elapsed($timer).')');

        // Pass 6 — Caregiver References
        $timer = microtime(true);
        $this->info("Pass 6/10: Caregiver References ($total records)...");
        $service->passCaregiverReferences($hits, $usersByBubbleId);
        $this->info('  Done. ('.$this->elapsed($timer).')');

        // Pass 7 — Caregiver Sponsors
        $timer = microtime(true);
        $this->info("Pass 7/10: Caregiver Sponsors ($total records)...");
        $service->passCaregiverSponsors($hits, $usersByBubbleId);
        $this->info('  Done. ('.$this->elapsed($timer).')');

        // Pass 8 — Caregiver Certifications
        $timer = microtime(true);
        $this->info("Pass 8/10: Caregiver Certifications ($total records)...");
        $service->passCaregiverCertifications($hits, $usersByBubbleId);
        $this->info('  Done. ('.$this->elapsed($timer).')');

        // Pass 9 — Caregiver Specialties & Attributes
        $timer = microtime(true);
        $this->info("Pass 9/10: Caregiver Specialties & Attributes ($total records)...");
        $service->passCaregiverSpecialties($hits, $usersByBubbleId);
        $service->passCaregiverAttributes($hits, $usersByBubbleId);
        $this->info('  Done. ('.$this->elapsed($timer).')');

        // Pass 10 — Client Addresses
        $timer = microtime(true);
        $this->info("Pass 10/10: Client Addresses ($total records)...");
        $service->passClientAddresses($hits, $usersByBubbleId);
        $this->info('  Done. ('.$this->elapsed($timer).')');

        // Finalize temporary caregiver slugs
        $timer = microtime(true);
        $this->info('Finalizing caregiver slugs...');
        $slugCount = $service->finalizeCaregiverSlugs();
        $this->info("  {$slugCount} slugs updated. (".$this->elapsed($timer).')');

        $this->newLine();
        $this->info('All passes complete.');
    }

    protected function elapsed(float $start): string
    {
        return round(microtime(true) - $start, 2).'s';
    }

    protected function importJobs(array $hits, bool $force): void
    {
        $service = app(ImportUserService::class);
        $total = count($hits);
        $timer = microtime(true);

        $this->info("Importing jobs ($total records)...");
        $bookingsByBubbleId = $service->passJobs($hits, $force);
        $this->info('  '.count($bookingsByBubbleId).' bookings in database. ('.$this->elapsed($timer).')');

        $this->newLine();
        $this->info('Jobs import complete.');
    }

    protected function importRatings(array $hits): void
    {
        $service = app(ImportUserService::class);
        $total = count($hits);
        $timer = microtime(true);

        $this->info("Importing ratings ($total records)...");
        $bookingsByBubbleId = Booking::whereNotNull('bubble_id')->get()->keyBy('bubble_id')->all();
        $service->passRatings($hits, $bookingsByBubbleId);
        $this->info('  Done. ('.$this->elapsed($timer).')');

        $this->newLine();
        $this->info('Ratings import complete.');
    }

    protected function importTransactions(array $hits): void
    {
        $service = app(ImportUserService::class);
        $total = count($hits);
        $timer = microtime(true);

        $this->info("Importing transactions ($total records)...");
        $bookingsByBubbleId = Booking::whereNotNull('bubble_id')->get()->keyBy('bubble_id')->all();
        $service->passTransactions($hits, $bookingsByBubbleId);
        $this->info('  Done. ('.$this->elapsed($timer).')');

        $this->newLine();
        $this->info('Transactions import complete.');
    }

    protected function initStagingDatabase(): void
    {
        $dbPath = storage_path('app/bubble_staging.sqlite');
        if (! file_exists($dbPath)) {
            $this->error("Staging database not found at {$dbPath}. Run 'import:bubble' first.");

            exit(1);
        }
        $this->sqlite = new \PDO("sqlite:$dbPath");
        $this->sqlite->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    protected function getStagedCount(string $type): int
    {
        $stmt = $this->sqlite->prepare('SELECT COUNT(*) FROM staged_records WHERE type = ?');
        $stmt->execute([$type]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array<int, array{source: array<string, mixed>, id: string}>
     */
    protected function loadHits(string $type, ?int $limit = null): array
    {
        $sql = 'SELECT raw_json FROM staged_records WHERE type = ? ORDER BY modified_at DESC';
        if ($limit) {
            $sql .= " LIMIT $limit";
        }

        $stmt = $this->sqlite->prepare($sql);
        $stmt->execute([$type]);

        $hits = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $decoded = json_decode($row['raw_json'], true);
            $hits[] = [
                'source' => $decoded,
                'id' => $decoded['_id'] ?? null,
            ];
        }

        return $hits;
    }

    protected function sortHitsByRole(array $hits): array
    {
        $rolePriority = [
            'admin' => 1,
            'caregiver' => 2,
            'caregiver_applicant' => 2,
            'client' => 3,
        ];

        usort($hits, function (array $a, array $b) use ($rolePriority) {
            $roleA = $a['source']['role_permissions_option_role'] ?? 'caregiver';
            $roleB = $b['source']['role_permissions_option_role'] ?? 'caregiver';
            $priorityA = $rolePriority[$roleA] ?? 9;
            $priorityB = $rolePriority[$roleB] ?? 9;

            return $priorityA <=> $priorityB;
        });

        return $hits;
    }
}
