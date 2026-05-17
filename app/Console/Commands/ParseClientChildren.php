<?php

namespace App\Console\Commands;

use App\Models\Client as ClientModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ParseClientChildren extends Command
{
    protected $signature = 'app:parse-client-children
        {--batch=15 : Records per API call}
        {--limit=0 : Max records to process (0 = all)}
        {--timeout=120 : API timeout in seconds}
        {--force : Re-parse even if cached}';

    protected $description = 'Parse client children from Bubble staging using AI';

    private ?\PDO $db = null;

    public function handle(): int
    {
        $this->db = $this->initDb();
        $records = $this->getRecords();

        if (empty($records)) {
            $this->info('No uncached records with children data found.');
            $this->line('   → Run with --force to re-parse cached records.');

            return Command::SUCCESS;
        }

        $total = count($records);

        $this->line("Found {$total} records to process.");
        $this->line('');

        $processed = 0;

        foreach (array_chunk($records, (int) $this->option('batch')) as $batch) {
            $batchNum = (int) ($processed / (int) $this->option('batch')) + 1;
            $this->line("── Batch {$batchNum} ──────────────────────────────");
            $this->line('Sending '.count($batch).' records to AI...');

            foreach ($batch as $item) {
                $this->line("  → {$item['external_id']}: \"{$item['text']}\"");
            }

            $start = microtime(true);
            $results = $this->callAI($batch);
            $elapsed = round(microtime(true) - $start, 1);

            if ($results === null) {
                $this->warn("  API call failed after {$elapsed}s, skipping batch.");

                continue;
            }

            $this->line('  Raw AI response:');
            foreach ($results as $eid => $children) {
                foreach ($children as $c) {
                    $name = $c['name'] ?? '?';
                    $y = $c['age_years'] ?? '-';
                    $m = $c['age_months'] ?? '-';
                    $gender = $c['gender'] ?? '?';
                    $this->line("    {$eid}: {$name}, {$y}y {$m}m, {$gender}");
                }
            }

            $saved = 0;

            foreach ($results as $eid => $children) {
                if (! $eid) {
                    continue;
                }

                $clientName = $this->saveToApp($eid, $children);

                if ($clientName) {
                    $this->line("  ✓ {$clientName}: ".count($children).' child'.(count($children) !== 1 ? 'ren' : '').' saved');
                    $this->cacheResult($eid, $children);
                    $saved++;
                } else {
                    $this->line("  ✗ {$eid}: client not found in app DB, skipped");
                }
            }

            $processed += count($batch);
            $this->line("  Batch done: {$saved}/".count($results).' clients updated.');
            $this->line("  Progress: {$processed}/{$total}");
            $this->line('');
        }

        $this->info("Done. Processed {$processed} records.");

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

    private function getRecords(): array
    {
        $limit = (int) $this->option('limit');
        $force = $this->option('force');

        $sql = "SELECT s.external_id, s.modified_at, s.raw_json
                FROM staged_records s
                WHERE s.type = 'user'
                  AND s.raw_json LIKE '%names_and_ages_of_kids_text%'";

        if (! $force) {
            $sql .= ' AND (s.external_id NOT IN (SELECT external_id FROM ai_caches)
                       OR s.modified_at > (SELECT COALESCE(MAX(modified_at), 0) FROM ai_caches WHERE external_id = s.external_id))';
        }

        $sql .= ' ORDER BY s.modified_at DESC';

        if ($limit > 0) {
            $sql .= " LIMIT {$limit}";
        }

        $stmt = $this->db->query($sql);
        $records = [];

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $data = json_decode($row['raw_json'], true);
            $text = $data['names_and_ages_of_kids_text'] ?? '';

            if (! trim($text)) {
                continue;
            }

            $records[] = [
                'external_id' => $row['external_id'],
                'modified_at' => $row['modified_at'],
                'text' => $text,
                'email' => $data['authentication']['email']['email'] ?? null,
            ];
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
                'Authorization' => 'Bearer '.config('services.openrouter.api_key'),
                'Content-Type' => 'application/json',
            ])
            ->post('https://openrouter.ai/api/v1/chat/completions', [
                'model' => config('services.openrouter.model'),
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

        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            $this->warn('  Response is not valid JSON.');

            return null;
        }

        return $decoded;
    }

    private function saveToApp(string $externalId, array $children): ?string
    {
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

        $clientName = $client->first_name.' '.$client->last_name;

        $client->children()->delete();

        foreach ($children as $child) {
            $name = $child['name'] ?? '';

            if (! $name) {
                continue;
            }

            $birthYear = null;
            $birthMonth = null;

            if (isset($child['age_months']) && $child['age_months'] !== null) {
                $months = (int) $child['age_months'];
                $years = (int) floor($months / 12);
                $remainingMonths = $months % 12;
                $birthYear = (int) date('Y') - $years;
                $birthMonth = (int) date('n') - $remainingMonths;

                if ($birthMonth <= 0) {
                    $birthMonth += 12;
                    $birthYear--;
                }
            } elseif (isset($child['age_years']) && $child['age_years'] !== null) {
                $birthYear = (int) date('Y') - (int) $child['age_years'];
            }

            $client->children()->create([
                'name' => $name,
                'birth_year' => $birthYear,
                'birth_month' => $birthMonth,
                'gender' => $child['gender'] ?? null,
            ]);
        }

        return $clientName;
    }

    private function cacheResult(string $externalId, array $children): void
    {
        $stmt = $this->db->prepare('SELECT modified_at FROM staged_records WHERE external_id = ?');
        $stmt->execute([$externalId]);
        $modifiedAt = (int) $stmt->fetchColumn();

        $stmt = $this->db->prepare('INSERT OR REPLACE INTO ai_caches (external_id, modified_at, children_json, created_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)');
        $stmt->execute([$externalId, $modifiedAt, json_encode($children)]);
    }
}
