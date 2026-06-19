<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateApplicantsData extends Command
{
    protected $signature = 'app:migrate-applicants-data
        {file? : Path to the JSON export file (import mode)}
        {--export : Export data from the current database to a JSON file}
        {--output= : Output path for export (default: storage/app/migration.json)}
        {--dry-run : Preview import without writing}';

    protected $description = 'Migrate caregiver applicant data between deployments';

    protected const TABLE_CONFIG = [
        'users' => ['order' => 1,  'remap' => null, 'unique' => 'email'],
        'interview_talking_points' => ['order' => 2,  'remap' => null, 'unique' => 'label'],
        'caregivers' => ['order' => 3,  'remap' => ['fk' => 'user_id', 'parent' => 'users']],
        'caregiver_applications' => ['order' => 4,  'remap' => ['fk' => 'caregiver_id', 'parent' => 'caregivers']],
        'caregiver_interviews' => ['order' => 5,  'remap' => [
            ['fk' => 'caregiver_id', 'parent' => 'caregivers'],
            ['fk' => 'evaluator_id', 'parent' => 'users'],
            ['fk' => 'application_id', 'parent' => 'caregiver_applications'],
        ]],
        'caregiver_interview_talking_points' => ['order' => 6,  'remap' => [
            ['fk' => 'caregiver_interview_id', 'parent' => 'caregiver_interviews'],
            ['fk' => 'talking_point_id', 'parent' => 'interview_talking_points'],
        ]],
        'caregiver_educations' => ['order' => 7,  'remap' => ['fk' => 'caregiver_id', 'parent' => 'caregivers']],
        'caregiver_certifications' => ['order' => 8,  'remap' => ['fk' => 'caregiver_id', 'parent' => 'caregivers']],
        'caregiver_specialties' => ['order' => 9,  'remap' => ['fk' => 'caregiver_id', 'parent' => 'caregivers']],
        'caregiver_locations' => ['order' => 10, 'remap' => ['fk' => 'caregiver_id', 'parent' => 'caregivers']],
        'caregiver_agreements' => ['order' => 11, 'remap' => ['fk' => 'caregiver_id', 'parent' => 'caregivers']],
        'caregiver_references' => ['order' => 12, 'remap' => ['fk' => 'caregiver_id', 'parent' => 'caregivers']],
        'caregiver_sponsors' => ['order' => 13, 'remap' => ['fk' => 'caregiver_id', 'parent' => 'caregivers']],
        'onboarding_checklist_items' => ['order' => 14, 'remap' => ['fk' => 'caregiver_id', 'parent' => 'caregivers']],
        'availabilities' => ['order' => 15, 'remap' => ['fk' => 'caregiver_id', 'parent' => 'caregivers']],
        'reference_requests' => ['order' => 16, 'remap' => ['fk' => 'caregiver_id', 'parent' => 'caregivers']],
        'entity_attribute_values' => ['order' => 17, 'remap' => ['fk' => 'entity_id', 'parent' => 'caregivers']],
        'incomplete_applications' => ['order' => 18, 'remap' => null, 'unique' => 'email'],
        'quick_links' => ['order' => 19, 'remap' => null],
    ];

    protected const JSON_COLUMNS = [
        'caregivers' => ['languages', 'metadata'],
        'caregiver_applications' => ['data'],
        'availabilities' => ['time_slots'],
        'caregiver_interviews' => ['scores'],
    ];

    protected const QUERY_TEMPLATES = [
        'users' => 'SELECT * FROM users WHERE id IN (SELECT user_id FROM caregivers WHERE id IN (SELECT caregiver_id FROM caregiver_applications) UNION SELECT DISTINCT evaluator_id FROM caregiver_interviews WHERE caregiver_id IN (SELECT caregiver_id FROM caregiver_applications))',
        'caregivers' => 'SELECT * FROM caregivers WHERE id IN (SELECT caregiver_id FROM caregiver_applications)',
        'caregiver_applications' => 'SELECT * FROM caregiver_applications',
    ];

    protected array $idMaps = [];

    protected array $stats = [];

    public function handle(): int
    {
        if ($this->option('export')) {
            return $this->export();
        }

        $file = $this->argument('file');

        if (! $file) {
            $this->error('Specify a JSON file to import, or use --export.');

            return Command::FAILURE;
        }

        return $this->import($file);
    }

    protected function export(): int
    {
        $output = $this->option('output') ?? storage_path('app/migration.json');
        $dir = dirname($output);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $caregiverIds = DB::select('SELECT GROUP_CONCAT(DISTINCT caregiver_id) AS ids FROM caregiver_applications');
        $ids = $caregiverIds[0]->ids ?? '';

        if (! $ids) {
            $this->warn('No caregiver applications found — nothing to export.');

            return Command::SUCCESS;
        }

        $this->info("Found caregiver IDs: {$ids}");
        $data = [];

        foreach (self::TABLE_CONFIG as $table => $config) {
            $query = self::QUERY_TEMPLATES[$table] ?? $this->buildQuery($table, $ids);

            $rows = DB::select($query);
            $rows = array_map(fn ($row) => (array) $row, $rows);

            if (! empty($rows)) {
                $rows = $this->decodeJsonColumns($table, $rows);
            }

            $data[$table] = $rows;
            $this->line(sprintf('  %s: %d rows', $table, count($rows)));
        }

        file_put_contents($output, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->newLine();
        $this->info("Exported to {$output}");

        return Command::SUCCESS;
    }

    protected function buildQuery(string $table, string $ids): string
    {
        if ($table === 'incomplete_applications') {
            return 'SELECT * FROM incomplete_applications WHERE email IN (SELECT u.email FROM users u JOIN caregivers c ON c.user_id = u.id JOIN caregiver_applications ca ON ca.caregiver_id = c.id)';
        }

        if ($table === 'caregiver_interview_talking_points') {
            return "SELECT citp.* FROM caregiver_interview_talking_points citp JOIN caregiver_interviews ci ON ci.id = citp.caregiver_interview_id WHERE ci.caregiver_id IN ({$ids})";
        }

        if (in_array($table, ['entity_attribute_values', 'interview_talking_points', 'quick_links'])) {
            return "SELECT * FROM {$table}";
        }

        return "SELECT * FROM {$table} WHERE caregiver_id IN ({$ids})";
    }

    protected function decodeJsonColumns(string $table, array $rows): array
    {
        $columns = self::JSON_COLUMNS[$table] ?? [];

        if (empty($columns)) {
            return $rows;
        }

        foreach ($rows as &$row) {
            foreach ($columns as $col) {
                if (isset($row[$col]) && is_string($row[$col])) {
                    $decoded = json_decode($row[$col], true);
                    $row[$col] = $decoded ?? $row[$col];
                }
            }
        }

        return $rows;
    }

    protected function import(string $file): int
    {
        if (! file_exists($file)) {
            $this->error("File not found: {$file}");

            return Command::FAILURE;
        }

        $contents = file_get_contents($file);
        $data = json_decode($contents, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Invalid JSON: '.json_last_error_msg());

            return Command::FAILURE;
        }

        $dryRun = $this->option('dry-run');

        // Validate structure — warn about missing/missing tables
        $missingTables = [];
        $this->validateStructure($data, $missingTables);

        if ($dryRun) {
            return $this->previewImport($data, $missingTables);
        }

        $this->info('Importing data...');
        $this->newLine();

        try {
            DB::transaction(function () use ($data) {
                foreach (self::TABLE_CONFIG as $table => $config) {
                    if (! isset($data[$table])) {
                        continue;
                    }

                    $rows = $data[$table];

                    if (empty($rows)) {
                        continue;
                    }

                    $this->importTable($table, $config, $rows);
                }

                $this->newLine();
                $this->verifyImport();
            });
        } catch (\Throwable $e) {
            $this->newLine();
            $this->error("Import failed and was rolled back: {$e->getMessage()}");

            return Command::FAILURE;
        }

        $this->printSummary();

        if (! empty($missingTables)) {
            $this->newLine();
            $this->warn('Missing tables in export (skipped): '.implode(', ', $missingTables));
        }

        return Command::SUCCESS;
    }

    protected function validateStructure(array $data, array &$missingTables): void
    {
        $expectedTables = array_keys(self::TABLE_CONFIG);

        foreach ($expectedTables as $table) {
            if (! isset($data[$table])) {
                $missingTables[] = $table;
                $this->warn("Missing table in export: {$table}");
            }
        }

        // Validate row structure
        foreach ($data as $table => $rows) {
            if (! is_array($rows) || (isset($rows[0]) && ! is_array($rows[0]))) {
                $this->warn("Table '{$table}' should be an array of objects");

                continue;
            }

            foreach ($rows as $i => $row) {
                if (! is_array($row) || empty($row)) {
                    $this->warn("  {$table}[{$i}]: empty or invalid row, skipping");

                    continue;
                }

                // Check for missing id
                if (! isset($row['id']) && $row['id'] !== null) {
                    $this->warn("  {$table}[{$i}]: missing 'id' field");
                }

                // Check unique field (email)
                if ($table === 'users' && ! isset($row['email'])) {
                    $this->warn("  users[{$i}]: missing 'email' field");
                }
            }
        }
    }

    protected function previewImport(array $data, array $missingTables): int
    {
        $this->info('Dry-run preview:');
        $this->newLine();

        foreach (self::TABLE_CONFIG as $table => $config) {
            $rows = $data[$table] ?? [];

            if (empty($rows)) {
                $this->line(sprintf('  %s: 0 rows (skipped)', $table));

                continue;
            }

            $remap = $config['remap'] ?? null;
            $remapLabel = '';
            if ($remap) {
                $remaps = isset($remap['fk']) ? [$remap] : $remap;
                $parts = array_map(fn ($r) => "{$r['fk']} ← new {$r['parent']}.id", $remaps);
                $remapLabel = ' ('.implode(', ', $parts).')';
            }
            $this->line(sprintf('  %s: %d rows%s', $table, count($rows), $remapLabel));
        }

        if (! empty($missingTables)) {
            $this->newLine();
            $this->warn('Missing tables: '.implode(', ', $missingTables));
        }

        $this->newLine();
        $this->info('Run without --dry-run to import.');

        return Command::SUCCESS;
    }

    protected function importTable(string $table, array $config, array $rows): void
    {
        $remap = $config['remap'] ?? null;
        $uniqueField = $config['unique'] ?? null;
        $inserted = 0;
        $skipped = 0;
        $errors = 0;

        if ($table === 'quick_links') {
            DB::table($table)->delete();
        }

        foreach ($rows as $row) {
            try {
                $oldId = $row['id'] ?? null;

                if ($oldId === null) {
                    $this->warn("  {$table}: row has no 'id', skipping");
                    $errors++;

                    continue;
                }

                // Check for unique constraint conflicts (e.g., duplicate email)
                if ($uniqueField && isset($row[$uniqueField])) {
                    $existing = DB::table($table)->where($uniqueField, $row[$uniqueField])->first();

                    if ($existing) {
                        $this->warn("  {$table}: '{$row[$uniqueField]}' already exists (id={$existing->id}), reusing existing");
                        $this->idMaps[$table][$oldId] = $existing->id;
                        $skipped++;

                        continue;
                    }
                }

                // Build insert data (exclude id)
                $insertData = $row;
                unset($insertData['id']);

                // Handle nullable timestamps — convert string to Carbon
                $insertData = $this->castTimestamps($insertData);

                // Remap FK(s)
                if ($remap) {
                    $remaps = isset($remap['fk']) ? [$remap] : $remap;

                    foreach ($remaps as $r) {
                        $parentMap = $this->idMaps[$r['parent']] ?? [];
                        $oldFk = $insertData[$r['fk']] ?? null;

                        if ($oldFk === null) {
                            continue;
                        }

                        if (! isset($parentMap[$oldFk])) {
                            $this->warn("  {$table}: row '{$oldId}' references missing {$r['parent']}.id={$oldFk}, skipping");
                            $errors++;

                            continue 2;
                        }

                        $insertData[$r['fk']] = $parentMap[$oldFk];
                    }
                }

                // Handle JSON columns — encode arrays/objects back to JSON strings
                $insertData = $this->encodeJsonColumns($table, $insertData);

                // Insert row
                $insertedId = DB::table($table)->insertGetId($insertData);

                if ($oldId !== null) {
                    $this->idMaps[$table][$oldId] = $insertedId;
                }

                $inserted++;
            } catch (\Throwable $e) {
                $identifier = $row['email'] ?? $row['name'] ?? $row['id'] ?? 'unknown';
                $this->warn("  {$table}[{$identifier}]: error — {$e->getMessage()}");
                $errors++;
            }
        }

        $this->stats[$table] = compact('inserted', 'skipped', 'errors');
    }

    protected function verifyImport(): void
    {
        $errors = [];

        foreach (self::TABLE_CONFIG as $table => $config) {
            $remap = $config['remap'] ?? null;

            if (! $remap) {
                continue;
            }

            $remaps = isset($remap['fk']) ? [$remap] : $remap;

            foreach ($remaps as $r) {
                $badRows = DB::table($table)
                    ->whereNotNull($r['fk'])
                    ->whereNotExists(function ($query) use ($table, $r) {
                        $query->select(DB::raw(1))
                            ->from($r['parent'])
                            ->whereRaw("{$r['parent']}.id = {$table}.{$r['fk']}");
                    })
                    ->get(['id', $r['fk']]);

                if ($badRows->isNotEmpty()) {
                    $count = $badRows->count();
                    $sample = $badRows->take(10);
                    $details = $sample->map(fn ($row) => "id={$row->id} ({$r['fk']}={$row->{$r['fk']}})")->implode(', ');
                    $more = $count > 10 ? ' (and '.($count - 10).' more)' : '';

                    $errors[] = "{$table}: {$count} invalid {$r['fk']} → {$r['parent']}.id: {$details}{$more}";
                }
            }
        }

        if (! empty($errors)) {
            throw new \RuntimeException(
                "FK integrity check failed:\n".implode("\n", array_map(
                    fn ($e) => "  - {$e}", $errors
                ))
            );
        }

        $this->line('  ✓ FK integrity verified');
    }

    protected function castTimestamps(array $data): array
    {
        $timestampColumns = ['created_at', 'updated_at', 'submitted_at', 'deleted_at', 'last_activity_at',
            'nudged_at', 'archived_at', 'signed_at', 'verified_at', 'expiration_date', 'date_of_birth',
            'last_login_at', 'email_verified_at', 'two_factor_confirmed_at', 'start_date', 'end_date',
        ];

        foreach ($timestampColumns as $col) {
            if (isset($data[$col]) && is_string($data[$col])) {
                try {
                    $data[$col] = Carbon::parse($data[$col]);
                } catch (\Throwable) {
                    // Leave as-is if it can't be parsed
                }
            }
        }

        return $data;
    }

    protected function encodeJsonColumns(string $table, array $data): array
    {
        $columns = self::JSON_COLUMNS[$table] ?? [];

        foreach ($columns as $col) {
            if (isset($data[$col]) && (is_array($data[$col]) || is_object($data[$col]))) {
                $data[$col] = json_encode($data[$col]);
            }
        }

        return $data;
    }

    protected function printSummary(): void
    {
        $this->newLine();
        $this->info('Import summary:');

        foreach (self::TABLE_CONFIG as $table => $config) {
            $stats = $this->stats[$table] ?? ['inserted' => 0, 'skipped' => 0, 'errors' => 0];

            if ($stats['inserted'] === 0 && $stats['skipped'] === 0) {
                continue;
            }

            $parts = ["inserted: {$stats['inserted']}"];

            if ($stats['skipped'] > 0) {
                $parts[] = "skipped: {$stats['skipped']}";
            }

            if ($stats['errors'] > 0) {
                $parts[] = "errors: {$stats['errors']}";
            }

            $this->line(sprintf('  %s: %s', $table, implode(', ', $parts)));
        }

        $this->newLine();
        $this->info('Done.');
    }
}
