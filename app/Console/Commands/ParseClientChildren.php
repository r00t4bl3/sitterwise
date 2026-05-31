<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\Client as ClientModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ParseClientChildren extends Command
{
    protected $signature = 'app:parse-children
        {--type=all : Types to process (user, jobs, or all)}
        {--batch=25 : Records per API call}
        {--limit=0 : Max records to process (0 = all)}
        {--timeout=120 : API timeout in seconds}
        {--force : Re-parse even if cached}
        {--from-cache : Import children from cached AI responses instead of calling AI}';

    protected $description = 'Parse children from Bubble staging using AI';

    private ?\PDO $db = null;

    public function handle(): int
    {
        $type = $this->option('type');

        if (! in_array($type, ['user', 'jobs', 'all'], true)) {
            $this->error('Invalid type. Must be user, jobs, or all.');

            return Command::INVALID;
        }

        if ($this->option('from-cache')) {
            return $this->handleFromCache();
        }

        $this->db = $this->initDb();
        $types = $type === 'all' ? ['user', 'jobs'] : [$type];

        foreach ($types as $currentType) {
            $records = $this->getRecords($currentType);

            if (empty($records)) {
                $this->info("No uncached {$currentType} records with children data found.");
                $this->line('   → Run with --force to re-parse cached records.');

                continue;
            }

            $total = count($records);
            $entityLabel = $currentType === 'user' ? 'clients' : 'bookings';

            $this->line("Found {$total} {$currentType} records to process.");
            $this->line('');

            $processed = 0;

            foreach (array_chunk($records, (int) $this->option('batch')) as $batch) {
                $batchNum = (int) ($processed / (int) $this->option('batch')) + 1;
                $this->line("── Batch {$batchNum} ──────────────────────────────");

                $cachedResults = [];
                $uncachedItems = [];

                foreach ($batch as $item) {
                    $cacheKey = $currentType === 'jobs' ? "jobs_{$item['external_id']}" : $item['external_id'];
                    $cached = $this->getCachedResult($cacheKey, $item['modified_at']);

                    if ($cached !== null) {
                        $cachedResults[$item['external_id']] = $cached;
                        $this->line("  ✓ {$item['external_id']}: loaded from cache");
                    } else {
                        $uncachedItems[] = $item;
                    }
                }

                if (empty($uncachedItems)) {
                    $this->line('  All records loaded from cache, skipping AI call.');
                    $results = $cachedResults;
                } else {
                    $this->line('Sending '.count($uncachedItems).' records to AI...');

                    foreach ($uncachedItems as $item) {
                        $this->line("  → {$item['external_id']}: \"{$item['text']}\"");
                    }

                    $start = microtime(true);
                    $aiResults = $this->callAI($uncachedItems);
                    $elapsed = round(microtime(true) - $start, 1);

                    if ($aiResults === null) {
                        $this->warn("  API call failed after {$elapsed}s.");

                        if ($cachedResults) {
                            $this->line('  Falling back to cached results for this batch.');
                            $results = $cachedResults;
                        } else {
                            $this->warn('  No cached fallback available, skipping batch.');

                            continue;
                        }
                    } else {
                        $this->line('  Raw AI response:');
                        foreach ($aiResults as $eid => $children) {
                            foreach ($children as $c) {
                                $name = $c['name'] ?? '?';
                                $y = $c['age_years'] ?? '-';
                                $m = $c['age_months'] ?? '-';
                                $gender = $c['gender'] ?? '?';
                                $this->line("    {$eid}: {$name}, {$y}y {$m}m, {$gender}");
                            }
                        }

                        $results = array_merge($cachedResults, $aiResults);
                    }
                }

                $saved = 0;

                foreach ($results as $eid => $children) {
                    if (! $eid) {
                        continue;
                    }

                    $entityName = $this->saveToApp($eid, $children, $currentType);

                    if ($entityName) {
                        $this->line('  ✓ '.$entityName.': '.count($children).' child'.(count($children) !== 1 ? 'ren' : '').' saved');
                        $cacheKey = $currentType === 'jobs' ? "jobs_{$eid}" : $eid;
                        $this->cacheResult($cacheKey, $eid, $children);
                        $saved++;
                    } else {
                        $this->line("  ✗ {$eid}: {$currentType} record not found in app DB, skipped");
                    }
                }

                $processed += count($batch);
                $this->line("  Batch done: {$saved}/".count($results)." {$entityLabel} updated.");
                $this->line("  Progress: {$processed}/{$total}");
                $this->line('');
            }

            $this->info("Done. Processed {$processed} {$currentType} records.");
        }

        return Command::SUCCESS;
    }

    private function handleFromCache(): int
    {
        $filterType = $this->option('type');
        $limit = (int) $this->option('limit');
        $batchSize = (int) $this->option('batch');

        $this->db = $this->initDb();

        $stmt = $this->db->query('SELECT external_id, children_json FROM ai_caches');
        $records = [];

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $cacheKey = $row['external_id'];

            if (str_starts_with($cacheKey, 'jobs_')) {
                $currentType = 'jobs';
                $externalId = substr($cacheKey, 5);
            } else {
                $currentType = 'user';
                $externalId = $cacheKey;
            }

            if ($filterType !== 'all' && $currentType !== $filterType) {
                continue;
            }

            $records[] = [
                'external_id' => $externalId,
                'type' => $currentType,
                'children' => json_decode($row['children_json'], true),
            ];
        }

        if (empty($records)) {
            $this->info('No cached records found.');

            return Command::SUCCESS;
        }

        $total = count($records);
        $this->line("Found {$total} cached records to import.");

        if ($limit > 0) {
            $records = array_slice($records, 0, $limit);
            $this->line("Limited to {$limit} records.");
        }

        $this->line('');
        $processed = 0;
        $imported = 0;

        foreach (array_chunk($records, $batchSize) as $batch) {
            $batchNum = (int) ($processed / $batchSize) + 1;
            $this->line("── Batch {$batchNum} ──────────────────────────────");

            foreach ($batch as $item) {
                $entityName = $this->saveToApp($item['external_id'], $item['children'], $item['type']);

                if ($entityName) {
                    $this->line('  ✓ '.$entityName.': '.count($item['children']).' child'.(count($item['children']) !== 1 ? 'ren' : '').' imported');
                    $cacheKey = $item['type'] === 'jobs' ? "jobs_{$item['external_id']}" : $item['external_id'];
                    $this->cacheResult($cacheKey, $item['external_id'], $item['children']);
                    $imported++;
                } else {
                    $this->line("  ✗ {$item['external_id']}: {$item['type']} record not found in app DB, skipped");
                }

                $processed++;
            }

            $this->line("  Progress: {$processed}/{$total}");
            $this->line('');
        }

        $this->info("Done. Imported {$imported}/{$total} records from cache.");

        return Command::SUCCESS;
    }

    private function initDb(): \PDO
    {
        $path = storage_path('app/bubble_staging.sqlite');
        $db = new \PDO("sqlite:{$path}");
        $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $db->exec('CREATE TABLE IF NOT EXISTS ai_caches (
            external_id TEXT PRIMARY KEY,
            modified_at INTEGER,
            children_json TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )');

        return $db;
    }

    private function getCachedResult(string $cacheKey, int $modifiedAt): ?array
    {
        $stmt = $this->db->prepare('SELECT children_json, modified_at FROM ai_caches WHERE external_id = ?');
        $stmt->execute([$cacheKey]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (! $row || (int) $row['modified_at'] < $modifiedAt) {
            return null;
        }

        return json_decode($row['children_json'], true);
    }

    private function getRecords(string $type): array
    {
        $limit = (int) $this->option('limit');
        $force = $this->option('force');

        $textField = $type === 'user' ? 'names_and_ages_of_kids_text' : 'names_and_ages_of_children_text';

        $sql = 'SELECT s.external_id, s.modified_at, s.raw_json
                FROM staged_records s
                WHERE s.type = ?
                  AND s.raw_json LIKE ?';

        if (! $force) {
            if ($type === 'jobs') {
                $sql .= " AND (('jobs_' || s.external_id) NOT IN (SELECT external_id FROM ai_caches)
                           OR s.modified_at > (SELECT COALESCE(MAX(modified_at), 0) FROM ai_caches WHERE external_id = 'jobs_' || s.external_id))";
            } else {
                $sql .= ' AND (s.external_id NOT IN (SELECT external_id FROM ai_caches)
                           OR s.modified_at > (SELECT COALESCE(MAX(modified_at), 0) FROM ai_caches WHERE external_id = s.external_id))';
            }
        }

        $sql .= ' ORDER BY s.modified_at DESC';

        if ($limit > 0) {
            $sql .= " LIMIT {$limit}";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$type, "%{$textField}%"]);
        $records = [];

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $data = json_decode($row['raw_json'], true);
            $text = $data[$textField] ?? '';

            if (! trim($text)) {
                continue;
            }

            $record = [
                'external_id' => $row['external_id'],
                'modified_at' => $row['modified_at'],
                'text' => $text,
            ];

            if ($type === 'user') {
                $record['email'] = $data['authentication']['email']['email'] ?? null;
            }

            $records[] = $record;
        }

        if ($type === 'jobs') {
            $ids = array_column($records, 'external_id');
            $existing = Booking::whereIn('bubble_id', $ids)->pluck('bubble_id')->toArray();

            $filtered = array_values(array_filter(
                $records,
                fn ($r) => in_array($r['external_id'], $existing, true),
            ));

            $skipped = count($records) - count($filtered);

            if ($skipped > 0) {
                $this->line("  Filtered out {$skipped} job IDs with no matching booking in app DB.");
            }

            $records = $filtered;
        }

        return $records;
    }

    private function callAI(array $batch): ?array
    {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            if ($attempt > 1) {
                $this->line("  Retry {$attempt}...");
            }

            $result = $this->callAIOnce($batch);

            if ($result !== null) {
                return $result;
            }
        }

        $this->error('  All 3 attempts failed.');

        return null;
    }

    private function callAIOnce(array $batch): ?array
    {
        $sections = [];

        foreach ($batch as $item) {
            $text = str_replace(["\r\n", "\r", "\n"], '; ', $item['text']);
            $sections[] = "[{$item['external_id']}] {$text}";
        }

        $prompt = 'Parse children from these texts. Return a JSON object where each key is an external_id and the value is an array of children.

Each child has: name (string), age_years (int|null), age_months (int|null), gender ("male","female",null).

Rules:
- If units are YEARS: e.g. "5", "5yo", "5yr", "5 years" → age_years: 5, age_months: null
- If units are MONTHS: e.g. "3 months", "18 months", "8 mos" → age_years: null, age_months: 3
- If no unit given, assume years: e.g. "6" → age_years: 6, age_months: null
- "0" → age_years: 0 (baby under 1)
- "infant" → age_years: 0
- "various ages" or unclear → empty array

Example: {"id1":[{"name":"Arthur","age_years":3,"age_months":null,"gender":"male"}],"id2":[{"name":"Yuma","age_years":null,"age_months":3,"gender":"female"}]}

Output ONLY a JSON object, no markdown, no explanation.'."\n\n".implode("\n", $sections);

        $response = Http::timeout((int) $this->option('timeout'))
            ->withHeaders([
                'Authorization' => 'Bearer '.config('services.ai_parser.api_key'),
                'Content-Type' => 'application/json',
            ])
            ->post(config('services.ai_parser.api_url'), [
                'model' => config('services.ai_parser.model'),
                'messages' => [
                    ['role' => 'system', 'content' => 'You output JSON only. Never wrap in markdown.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0,
                'reasoning' => ['enabled' => false],
            ]);

        if (! $response->successful()) {
            $this->error('API error: '.$response->body());

            return null;
        }

        $body = $response->json();
        $content = $body['choices'][0]['message']['content'] ?? null;

        $this->line('  Raw response:');
        foreach (explode("\n", trim($content ?? '(empty)')) as $line) {
            $this->line('    '.$line);
        }

        if (! $content) {
            $this->warn('  Empty AI response.');

            return null;
        }

        $content = $this->extractJsonFromMarkdown($content);
        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            $this->warn('  Response is not valid JSON.');

            return null;
        }

        return $decoded;
    }

    private function extractJsonFromMarkdown(string $content): string
    {
        $content = trim($content);

        if (preg_match('/^```(?:json)?\s*\n?(.*?)\n?```$/s', $content, $matches)) {
            return trim($matches[1]);
        }

        return $content;
    }

    private function saveToApp(string $externalId, array $children, string $type): ?string
    {
        if ($type === 'jobs') {
            $booking = Booking::where('bubble_id', $externalId)->first();

            if (! $booking) {
                return null;
            }

            $formattedChildren = [];

            foreach ($children as $child) {
                $name = $child['name'] ?? '';

                if (! $name) {
                    continue;
                }

                $birthMonth = null;
                $birthYear = null;

                if (isset($child['age_months']) && $child['age_months'] !== null && $child['age_months'] !== '') {
                    $birthDate = now()->subMonths((int) $child['age_months']);
                    $birthMonth = (int) $birthDate->format('n');
                    $birthYear = (int) $birthDate->format('Y');
                } elseif (isset($child['age_years']) && $child['age_years'] !== null && $child['age_years'] !== '') {
                    $birthYear = (int) now()->subYears((int) $child['age_years'])->format('Y');
                }

                $formattedChildren[] = [
                    'name' => $name,
                    'gender' => $child['gender'] ?: null,
                    'birth_month' => $birthMonth,
                    'birth_year' => $birthYear,
                ];
            }

            $booking->children = $formattedChildren;
            Booking::withoutEvents(fn () => $booking->save());

            return "booking {$externalId}";
        }

        $stmt = $this->db->prepare('SELECT raw_json FROM staged_records WHERE external_id = ?');
        $stmt->execute([$externalId]);
        $raw = $stmt->fetchColumn();

        if (! $raw) {
            return null;
        }

        $data = json_decode($raw, true);
        $email = $data['authentication']['email']['email'] ?? null;

        if (! $email) {
            return null;
        }

        $client = ClientModel::whereHas('user', fn ($q) => $q->where('email', $email))->first();

        if (! $client) {
            return null;
        }

        if ($client->bookings()->where('service_type', 'group_childcare_invoiced')->exists()) {
            return null;
        }

        $clientName = $client->first_name.' '.$client->last_name;

        $client->children()->delete();

        foreach ($children as $child) {
            $name = $child['name'] ?? '';

            if (! $name) {
                continue;
            }

            $birthDate = null;

            if (isset($child['age_months']) && $child['age_months'] !== null && $child['age_months'] !== '') {
                $birthDate = now()->subMonths((int) $child['age_months']);
            } elseif (isset($child['age_years']) && $child['age_years'] !== null && $child['age_years'] !== '') {
                $birthDate = now()->subYears((int) $child['age_years']);
            }

            $client->children()->create([
                'name' => $name,
                'birth_date' => $birthDate,
                'gender' => $child['gender'] ?: null,
            ]);
        }

        return $clientName;
    }

    private function cacheResult(string $cacheKey, string $originalExternalId, array $children): void
    {
        $stmt = $this->db->prepare('SELECT modified_at FROM staged_records WHERE external_id = ?');
        $stmt->execute([$originalExternalId]);
        $modifiedAt = (int) $stmt->fetchColumn();

        $stmt = $this->db->prepare('INSERT OR REPLACE INTO ai_caches (external_id, modified_at, children_json, created_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)');
        $stmt->execute([$cacheKey, $modifiedAt, json_encode($children)]);
    }
}
