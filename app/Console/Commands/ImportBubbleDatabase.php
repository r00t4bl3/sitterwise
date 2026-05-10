<?php

namespace App\Console\Commands;

use App\Models\AttributeDefinition;
use App\Models\Caregiver;
use App\Models\CaregiverStatus;
use App\Models\CertificationType;
use App\Models\SpecialtyType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Panther\Client;

#[Signature('import:bubble {type? : The Bubble data type to import (user, jobs, rating, transactions)} {--url= : Custom Bubble URL to scrape} {--limit= : Limit number of records} {--force : Overwrite existing records} {--dry-run : Preview without saving} {--fresh : Force a fresh scrape from Bubble.io, ignoring staged data} {--insert : Process into App DB as we go} {--staged-only : Process only from local staging database without hitting the web} {--after= : Only sync records modified after this date (YYYY-MM-DD)} {--before= : Only sync records modified before this date (YYYY-MM-DD)}')]
#[Description('Import data from Bubble.io editor Data tab')]
class ImportBubbleDatabase extends Command
{
    protected const BUBBLE_BASE_URL = 'https://bubble.io/page?id=hello-76539&tab=Data&name=index&subtab=App+data&version=live';

    protected const SUPPORTED_TYPES = ['user', 'jobs', 'rating', 'transactions'];

    protected $successCount = 0;

    protected $syncedCount = 0;

    protected $errorCount = 0;

    protected $errors = [];

    protected ?\PDO $sqlite = null;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $type = $this->argument('type');
        $limit = (int) $this->option('limit');
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');
        $freshScrape = $this->option('fresh');
        $liveInsert = $this->option('insert');
        $stagedOnly = $this->option('staged-only');
        $afterDate = $this->option('after');
        $beforeDate = $this->option('before');

        $typesToProcess = $type ? [$type] : self::SUPPORTED_TYPES;

        $this->initStagingDatabase();

        $client = null;
        $userDataDir = null;

        try {
            foreach ($typesToProcess as $currentType) {
                $this->successCount = 0;
                $this->syncedCount = 0;
                $this->errorCount = 0;

                $url = $this->option('url') ?? self::BUBBLE_BASE_URL.'&type_id='.$currentType;
                $stagedCount = $this->getStagedCount($currentType);
                $isComplete = $this->isTypeComplete($currentType);

                $this->info("Starting Bubble sync for type [{$currentType}]");
                $this->line("    [Status] Staged Records: $stagedCount, Fully Synced Before: ".($isComplete ? 'Yes' : 'No'));

                if ($afterDate || $beforeDate) {
                    $this->line('    [Filter] Date Range: '.($afterDate ?: 'ANY').' to '.($beforeDate ?: 'ANY'));
                }

                if ($dryRun) {
                    $this->warn('Running in dry-run mode.');
                }

                try {
                    // Scenario 1: Process ONLY from staging (Offline mode)
                    if ($stagedOnly) {
                        if ($stagedCount === 0) {
                            $this->warn("No staged data found for [{$currentType}]. Skipping.");

                            continue;
                        }
                        $this->info("Processing [{$currentType}] from local staging database only...");
                        $hits = $this->getStagedRecords($currentType, $limit ?: null);
                        $this->processHits($currentType, $hits, $force, $dryRun);
                    }
                    // Scenario 2: Sync from Web (Default or --fresh)
                    else {
                        if ($freshScrape) {
                            $this->info("Forcing fresh scrape from Bubble.io for [{$currentType}]...");
                            $this->markTypeComplete($currentType, false);
                        }

                        if (! $client) {
                            $this->freePort(9515);
                            $this->ensureChromeInstalled();
                            $chromeDriverPath = $this->ensureChromeDriverInstalled();
                            $this->info('Launching headless Chrome browser...');
                            $userDataDir = sys_get_temp_dir().'/panther_chrome_'.uniqid();
                            @mkdir($userDataDir, 0755, true);

                            $client = Client::createChromeClient(
                                $chromeDriverPath,
                                [
                                    '--headless=new',
                                    '--no-sandbox',
                                    '--disable-dev-shm-usage',
                                    '--window-size=1920,1080',
                                    '--user-data-dir='.$userDataDir,
                                ]
                            );
                        }

                        $constraints = [];
                        if ($afterDate) {
                            $constraints[] = ['field' => 'Modified Date', 'operator' => '≥', 'value' => Carbon::parse($afterDate)->startOfDay()->format('m/d/Y h:i a')];
                        }
                        if ($beforeDate) {
                            $constraints[] = ['field' => 'Modified Date', 'operator' => '≤', 'value' => Carbon::parse($beforeDate)->endOfDay()->format('m/d/Y h:i a')];
                        }

                        $this->scrapeAndSync($client, $currentType, $url, $limit, $liveInsert, $force, $dryRun, $constraints);
                    }

                    $this->newLine();
                    $this->info("Complete for [{$currentType}]:");
                    $this->line("    - Staged (SQLite): {$this->syncedCount} new/updated");
                    $this->line("    - Processed (App DB): {$this->successCount} records");
                    $this->newLine();

                } catch (\Exception $e) {
                    $this->error("Failed for [{$currentType}]: ".$e->getMessage());
                    if (! $type) {
                        continue;
                    }

                    return Command::FAILURE;
                }
            }
        } finally {
            if ($client) {
                $client->quit();
            }
            if ($userDataDir) {
                $this->removeDirectory($userDataDir);
            }
        }

        return Command::SUCCESS;
    }

    protected function clickLoadMore(Client $client): string
    {
        return $client->executeScript("
            const findBtn = () => {
                // Try class first
                let btn = document.querySelector('.light-button.load-more');
                if (btn) return btn;
                
                // Try by text as fallback (flexible matching for 'Load 50 more items...')
                const buttons = document.querySelectorAll('.light-button, .bubble-element.Button');
                for (const b of buttons) {
                    const txt = b.textContent.toLowerCase();
                    if (txt.includes('load') && txt.includes('more')) return b;
                }
                return null;
            };

            const btn = findBtn();
            if (btn) {
                btn.scrollIntoView();
                // In Bubble, 'disabled' is often a class. offsetParent check is for visibility.
                const isVisible = btn.offsetParent !== null || btn.style.display !== 'none';
                const isDisabled = btn.classList.contains('disabled') || btn.disabled;
                
                if (isVisible && !isDisabled) {
                    btn.click();
                    return 'clicked';
                }
                return isVisible ? 'disabled' : 'hidden';
            }
            
            // If button not found, aggressively scroll all potential containers to trigger lazy-loading
            const containers = ['.table-container', '.bubble-element.RepeatingGroup', 'body'];
            for (const selector of containers) {
                const el = (selector === 'body') ? window : document.querySelector(selector);
                if (el) {
                    if (el === window) window.scrollTo(0, document.body.scrollHeight || 100000);
                    else el.scrollTop = el.scrollHeight || 100000;
                }
            }
            
            return 'not_found';
        ");
    }

    protected function initStagingDatabase(): void
    {
        $dbPath = storage_path('app/bubble_staging.sqlite');
        $this->sqlite = new \PDO("sqlite:$dbPath");
        $this->sqlite->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->sqlite->exec('
            CREATE TABLE IF NOT EXISTS staged_records (
                type TEXT,
                external_id TEXT,
                modified_at INTEGER,
                raw_json TEXT,
                last_synced_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                last_imported_at DATETIME,
                PRIMARY KEY (type, external_id)
            )
        ');
        $this->sqlite->exec('
            CREATE TABLE IF NOT EXISTS sync_metadata (
                type TEXT PRIMARY KEY,
                is_complete INTEGER DEFAULT 0,
                last_full_sync_at DATETIME
            )
        ');
    }

    protected function isTypeComplete(string $type): bool
    {
        $stmt = $this->sqlite->prepare('SELECT is_complete FROM sync_metadata WHERE type = ?');
        $stmt->execute([$type]);

        return (bool) $stmt->fetchColumn();
    }

    protected function markTypeComplete(string $type, bool $complete = true): void
    {
        $stmt = $this->sqlite->prepare('INSERT INTO sync_metadata (type, is_complete, last_full_sync_at) VALUES (?, ?, CURRENT_TIMESTAMP) ON CONFLICT(type) DO UPDATE SET is_complete = ?, last_full_sync_at = CURRENT_TIMESTAMP');
        $stmt->execute([$type, (int) $complete, (int) $complete]);
    }

    protected function getStagedCount(string $type): int
    {
        $stmt = $this->sqlite->prepare('SELECT COUNT(*) FROM staged_records WHERE type = ?');
        $stmt->execute([$type]);

        return (int) $stmt->fetchColumn();
    }

    protected function getStagedRecords(string $type, ?int $limit = null): array
    {
        $sql = 'SELECT raw_json FROM staged_records WHERE type = ? ORDER BY modified_at DESC';
        if ($limit) {
            $sql .= " LIMIT $limit";
        }
        $stmt = $this->sqlite->prepare($sql);
        $stmt->execute([$type]);
        $records = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $records[] = ['_source' => json_decode($row['raw_json'], true), '_id' => json_decode($row['raw_json'], true)['_id'] ?? null];
        }

        return $records;
    }

    protected function upsertStagedRecord(string $type, array $hit): void
    {
        $source = $hit['_source'] ?? [];
        $externalId = $hit['_id'] ?? null;
        $modifiedAt = $source['Modified Date'] ?? 0;
        $stmt = $this->sqlite->prepare('INSERT OR REPLACE INTO staged_records (type, external_id, modified_at, raw_json, last_synced_at) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)');
        $stmt->execute([$type, $externalId, $modifiedAt, json_encode($source)]);
    }

    protected function applyConstraints(Client $client, array $constraints): void
    {
        if (empty($constraints)) {
            return;
        }

        $this->info('Applying server-side constraints...');

        foreach ($constraints as $index => $c) {
            $this->line("  -> Adding constraint: {$c['field']} {$c['operator']} {$c['value']}");

            // 1. Click 'Add constraint'
            $client->executeScript("document.querySelector('.add-constraint')?.click();");
            sleep(2); // Wait for row to appear

            // 2. Select Field
            $this->selectBubbleDropdown($client, '.composer-dropdown.bubble-ui.constraint-field', $c['field']);

            // 3. Select Operator
            $this->selectBubbleDropdown($client, '.composer-dropdown.bubble-ui.operator-field', $c['operator']);

            // 4. Enter Value
            $client->executeScript("
                const inputs = document.querySelectorAll('.property-editor-control.bubble-ui.value-field');
                const input = inputs[inputs.length - 1];
                if (input) {
                    input.value = '{$c['value']}';
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                    input.dispatchEvent(new Event('blur', { bubbles: true }));
                }
            ");
            sleep(1000);
        }

        $this->info('Constraints applied.');
        sleep(2);
    }

    protected function selectBubbleDropdown(Client $client, string $containerSelector, string $searchText): void
    {
        // Try multiple times to handle timing issues
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->line("    Attempt {$attempt} to select dropdown: {$searchText}");

            // Click the dropdown container
            sleep(1);
            $this->line('    Clicking dropdown');
            $dropdownClicked = $client->executeScript("
                const containers = document.querySelectorAll('{$containerSelector}');
                const container = containers[containers.length - 1];
                if (container) {
                    const dropdownTrigger = container.querySelector('.property-editor-control');
                    if (dropdownTrigger) {
                        console.log('clicking dropdown', dropdownTrigger);
                        dropdownTrigger.dispatchEvent(new Event('mousedown', { bubbles: true, cancelable: true }));
                        return true;
                    }
                }
                return false;
            ");

            sleep(1);

            if ($dropdownClicked) {
                $this->info("    Successfully clicked: {$containerSelector}");
            } else {
                $this->warn("    Could not find matching item on attempt {$attempt}");
            }

            sleep(1);

            $this->line('    Selecting option');
            $optionClicked = $client->executeScript("
                targetOption = [...document.querySelectorAll('.dropdown-option')].find(el => el.textContent.trim() === '{$searchText}');
                if (targetOption) {
                    console.log('clicking option', targetOption);
                    targetOption.click();
                    return true;
                }
                return false;
            ");

            if ($optionClicked) {
                $this->info("    Successfully clicked: {$searchText}");

                return;
            } else {
                $this->warn("    Could not find matching item on attempt {$attempt}");
            }
        }

        $this->error("    Failed to select dropdown after 3 attempts: {$searchText}");
    }

    protected function scrapeAndSync(Client $client, string $type, string $url, int $limit, bool $liveInsert, bool $force, bool $dryRun, array $constraints = []): void
    {
        try {
            $this->info("Loading URL: {$url}");
            $client->request('GET', $url);
            $client->waitFor('body', 30);
            sleep(5);

            $this->info('Dismissing potential popups...');
            $dismissScript = "
                const dismiss = () => {
                    // Title-based cancel button
                    const cancelBtn = document.querySelector('.popup-title')?.parentElement?.querySelector('.btn-cancel');
                    if (cancelBtn) cancelBtn.click();
                    
                    // Specific buttons in modals (SAVE, Later, Not now, etc.)
                    const buttons = document.querySelectorAll('.bubble-element.Button, .btn, .button');
                    const targetTexts = ['SAVE', 'Save', 'Later', 'Not now', 'Ignore', 'Update', 'Upgrade'];
                    
                    for (const b of buttons) {
                        const txt = b.textContent.trim();
                        if (targetTexts.some(t => txt.includes(t))) {
                             // Only click if it's in a modal/popup/overlay
                             if (b.closest('.modal-view') || b.closest('.popup') || b.closest('.overlay')) {
                                b.click();
                             }
                        }
                    }
                    
                    // General close icons
                    const closeIcons = document.querySelectorAll('.fa-times, .close-icon, .close-button');
                    for (const icon of closeIcons) icon.click();
                };
                dismiss();
            ";
            $client->executeScript($dismissScript);
            sleep(2);

            $this->setupResponseInterceptor($client);

            $this->info('Navigating to App Data tab...');
            $client->executeScript($dismissScript); // Dismiss again in case navigation triggered a popup
            $client->executeScript("const tabs = document.querySelectorAll('.tab-caption'); for (const t of tabs) if (t.textContent.includes('App data')) t.click();");
            sleep(5);

            $this->info("Selecting [{$type}] from the sidebar...");
            $client->executeScript($dismissScript); // One more time before sidebar interaction
            $client->executeScript("
                const typeName = '{$type}';
                const items = document.querySelectorAll('.list-item');
                for (const item of items) {
                    if (item.textContent.trim().toLowerCase() === typeName.toLowerCase()) {
                        item.click();
                        return;
                    }
                }
            ");
            $this->info('Configuring fields...');
            $client->executeScript("const btn = Array.from(document.querySelectorAll('.light-button')).find(b => b.textContent.includes('additional fields')); if (btn) btn.click();");
            sleep(3);
            $client->executeScript("document.querySelector('.select-all')?.click();");
            sleep(1);

            $this->applyConstraints($client, $constraints);

            sleep(2);

            $this->info('Ensuring Modified Date sorting (Descending)...');
            $client->executeScript("
                const headers = document.querySelectorAll('.table-header-content');
                for (const h of headers) {
                    if (h.textContent.includes('Modified Date')) {
                        h.click();
                        setTimeout(() => h.click(), 1500);
                        return;
                    }
                }
            ");

            $client->executeScript("document.querySelector('.btn-create.bubble-ui')?.click();");
            // Wait for fields to be applied
            sleep(5);

            // Wait for the final sorted & filtered XHR to trigger and UI to settle
            $this->info('Starting data capture...');
            sleep(2);

            $page = 1;
            $totalSynced = 0;
            $hasFastForwarded = false;
            $stagedCount = $this->getStagedCount($type);
            $isComplete = $this->isTypeComplete($type);

            while (true) {
                $this->info("Intercepting page $page...");
                $responseData = $this->waitForInterceptedResponse($client, 60);

                if ($responseData && isset($responseData['hits']['hits'])) {
                    $hits = $responseData['hits']['hits'];
                    $batchNewOrUpdated = 0;
                    $batchPerfectMatches = 0;

                    if (count($hits) > 0) {
                        $firstHit = $hits[0]['_source'] ?? [];
                        $firstId = $hits[0]['_id'] ?? 'N/A';
                        $firstModified = $this->timestampToDate($firstHit['Modified Date'] ?? null) ?? 'N/A';
                        $this->line("    [Debug] Page $page starts with: ID: $firstId, Modified: $firstModified");
                    }

                    foreach ($hits as $hit) {
                        $source = $hit['_source'] ?? [];
                        $externalId = $hit['_id'] ?? null;
                        $modifiedAt = $source['Modified Date'] ?? 0;

                        // Check if we have this exact version
                        $stmt = $this->sqlite->prepare('SELECT COUNT(*) FROM staged_records WHERE type = ? AND external_id = ? AND modified_at = ?');
                        $stmt->execute([$type, $externalId, $modifiedAt]);

                        if ((bool) $stmt->fetchColumn()) {
                            $batchPerfectMatches++;
                        } else {
                            $this->upsertStagedRecord($type, $hit);
                            $batchNewOrUpdated++;
                            $this->syncedCount++;
                        }

                        if ($liveInsert) {
                            $this->processHits($type, [$hit], $force, $dryRun);
                        }
                    }

                    $this->info("  Results: {$batchNewOrUpdated} New/Updated, {$batchPerfectMatches} Perfect Matches");
                    $totalSynced += count($hits);

                    // End of Data detection: Batch size < 50 is a definitive end signal in Bubble
                    if (count($hits) < 50 && count($hits) > 0) {
                        $this->info("Reached the absolute end of data for [{$type}] (Batch size ".count($hits).' < 50).');
                        if (empty($constraints)) {
                            $this->markTypeComplete($type);
                        } else {
                            $this->line('    [Note] Completion marker skipped because filters are active.');
                        }
                        break;
                    }

                    // Check for Smart Stop or Fast-Forward trigger
                    if (count($hits) > 0 && $batchNewOrUpdated === 0 && $limit === 0) {
                        if ($isComplete) {
                            $this->info('Fully caught up with local staging (Type is marked complete). Stopping.');
                            break;
                        }

                        // IMPORTANT: Only fast-forward if we are NOT using server-side filters.
                        // Filters change the offset context, making stagedCount an invalid skip indicator.
                        if (! $hasFastForwarded && $stagedCount > 50 && empty($constraints)) {
                            $skipCount = floor($stagedCount / 50) - $page;
                            if ($skipCount > 0) {
                                $this->info("Found perfectly synced batch. Triggering automatic fast-forward (skipping {$skipCount} pages)...");

                                // Disable interception during the skip
                                $client->executeScript('window._capturedResponse = null;');

                                for ($i = 0; $i < $skipCount; $i++) {
                                    $this->line('  Skipping page '.($page + $i + 1).' of '.($page + $skipCount).'...');
                                    $status = 'not_found';
                                    for ($retry = 0; $retry < 8; $retry++) {
                                        $status = $this->clickLoadMore($client);
                                        if ($status === 'clicked') {
                                            break;
                                        }
                                        // If it's disabled, Bubble is likely still processing the last click or current page
                                        usleep(800000);
                                    }

                                    if ($status !== 'clicked' && $status !== 'disabled') {
                                        $this->warn('  Warning: Skip halted at step '.($i + 1)." (Status: $status). Transitioning to capture.");
                                        break;
                                    }

                                    // Slower pace for the skip loop to prevent UI deadlock
                                    usleep(1500000);
                                }

                                $hasFastForwarded = true;
                                $page += $skipCount;
                                $this->info("Fast-forward complete. Resuming capture at page $page.");

                                $client->executeScript('window._capturedResponse = null;');

                                $this->info('Fetching first fresh page...');
                                $loadMoreSuccess = false;
                                for ($retry = 0; $retry < 15; $retry++) {
                                    $status = $this->clickLoadMore($client);
                                    if ($status === 'clicked') {
                                        $loadMoreSuccess = true;
                                        break;
                                    }

                                    // If it's already disabled, it might have been clicked by the last skip step or is already loading
                                    if ($status === 'disabled') {
                                        $this->info('  Button is busy (disabled). Waiting for data to arrive...');
                                        $loadMoreSuccess = true;
                                        break;
                                    }

                                    $this->line("    [Debug] Fresh page retry $retry: Status is $status...");
                                    sleep(2);
                                }

                                if (! $loadMoreSuccess) {
                                    $this->info('Could not find next page after fast-forward. Stopping.');
                                    break;
                                }
                                $page++;

                                continue;
                            }
                        }
                    }

                    if ($limit && $totalSynced >= $limit) {
                        break;
                    }

                    $client->executeScript('window._capturedResponse = null;');

                    // Final check for Load More with retries
                    $loadMoreSuccess = false;
                    for ($retry = 0; $retry < 15; $retry++) {
                        $status = $this->clickLoadMore($client);
                        if ($status === 'clicked') {
                            $loadMoreSuccess = true;
                            break;
                        }

                        // If it's not found at all after many retries, then we are done
                        if ($status === 'not_found' && $retry > 5) {
                            break;
                        }

                        $this->line("    [Debug] Retry $retry: 'Load more' button is $status. Retrying...");
                        sleep(2);
                    }

                    if (! $loadMoreSuccess) {
                        $this->info("Reached the end of data for [{$type}] (No 'Load more' button found after retries).");
                        if (empty($constraints)) {
                            $this->markTypeComplete($type);
                        } else {
                            $this->line('    [Note] Completion marker skipped because filters are active.');
                        }
                        break;
                    }

                    $page++;
                } else {
                    $this->warn("Failed to intercept page $page");
                    break;
                }
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }

    protected function processHits(string $type, array $hits, bool $force, bool $dryRun): void
    {
        foreach ($hits as $index => $hit) {
            $source = $hit['_source'] ?? [];
            if ($type === 'user') {
                $name = ($source['first_name_text'] ?? 'Unknown').' '.($source['last_name_text'] ?? '');
                $modifiedDate = $this->timestampToDate($source['Modified Date'] ?? null);
                $this->line("    -> Processing: $name (Modified: $modifiedDate)");
                $validation = $this->validateRecord($source);
                if (! $validation['valid']) {
                    $this->error('       Validation failed: '.implode(', ', $validation['errors']));
                    $this->errorCount++;

                    continue;
                }
                if (! $dryRun) {
                    try {
                        DB::transaction(fn () => $this->importCaregiver($source, $force));
                        $this->successCount++;
                    } catch (\Exception $e) {
                        $this->error('       Import failed: '.$e->getMessage());
                        $this->errorCount++;
                    }
                } else {
                    $this->successCount++;
                }
            } else {
                $this->warn("Processing for type [{$type}] is not yet implemented.");
                $this->successCount++;
            }
        }
    }

    protected function validateRecord(array $source): array
    {
        $errors = [];
        $email = $source['authentication']['email']['email'] ?? null;
        if (! $email) {
            $errors[] = 'Missing email';
        } elseif (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format';
        }
        if (empty($source['first_name_text'])) {
            $errors[] = 'Missing first name';
        }
        if (empty($source['last_name_text'])) {
            $errors[] = 'Missing last name';
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    protected function importCaregiver(array $source, bool $force): void
    {
        $email = $source['authentication']['email']['email'];
        $firstName = $source['first_name_text'] ?? '';
        $lastName = $source['last_name_text'] ?? '';
        $user = User::updateOrCreate(['email' => $email], [
            'name' => "$firstName $lastName",
            'password' => Hash::make($source['temporary_password_text'] ?? 'changeme123'),
            'role' => $source['role_permissions_option_role'] ?? 'caregiver',
            'profile_photo_url' => $source['profile_photo_url_text'] ?? null,
        ]);
        $statusName = $source['cg_status_option_cg_status_options'] ?? 'inactive';
        $status = CaregiverStatus::firstOrCreate(['name' => $statusName]);

        $caregiverData = [
            'user_id' => $user->id, 'status_id' => $status->id,
            'first_name' => $firstName, 'last_name' => $lastName,
            'phone' => $source['phone_text'] ?? null,
            'address' => $source['address_geographic_address']['address'] ?? null,
            'date_of_birth' => $this->timestampToDate($source['date_of_birth_date'] ?? null),
            'biography' => $source['bio_text'] ?? null, 'notes' => $source['internal_notes_text'] ?? null,
        ];
        $caregiver = Caregiver::updateOrCreate(['user_id' => $user->id], $caregiverData);
        $this->importEducations($caregiver, $source);
        $this->importExperiences($caregiver, $source);
        $this->importReferences($caregiver, $source);
        $this->importSponsors($caregiver, $source);
        $this->importCertifications($caregiver, $source);
        $this->importSpecialties($caregiver, $source);
        $this->importAttributes($caregiver, $source);
    }

    protected function importEducations(Caregiver $caregiver, array $source): void
    {
        $caregiver->educations()->delete();
        if (! empty($source['high_school_name_text'])) {
            $caregiver->educations()->create(['education_type' => 'high_school', 'school_name' => $source['high_school_name_text'], 'graduation_year' => $this->timestampToYear($source['graduation_year_date'] ?? null)]);
        }
        if (! empty($source['college_name_text'])) {
            $caregiver->educations()->create(['education_type' => 'college', 'school_name' => $source['college_name_text'], 'graduation_year' => $this->timestampToYear($source['college_graduation_year_text'] ?? null)]);
        }
    }

    protected function importExperiences(Caregiver $caregiver, array $source): void
    {
        $caregiver->experiences()->delete();
        for ($i = 1; $i <= 3; $i++) {
            $startKey = "childcare_experience_{$i}_start_date_date";
            $endKey = "childcare_experience_{$i}_end_date_date";
            $detailsKey = "childcare_experience_{$i}_details_text";
            if (! empty($source[$startKey]) || ! empty($source[$detailsKey])) {
                $caregiver->experiences()->create(['sequence' => $i, 'start_date' => $this->timestampToDate($source[$startKey] ?? null), 'end_date' => $this->timestampToDate($source[$endKey] ?? null), 'details' => $source[$detailsKey] ?? null]);
            }
        }
    }

    protected function importReferences(Caregiver $caregiver, array $source): void
    {
        $caregiver->references()->delete();
        $references = $source['previous_caregivers_list_text'] ?? [];
        if (is_string($references)) {
            $references = array_map('trim', explode(',', $references));
        }
        foreach ((array) $references as $name) {
            if (! empty(trim($name))) {
                $caregiver->references()->create(['reference_name' => trim($name)]);
            }
        }
    }

    protected function importSponsors(Caregiver $caregiver, array $source): void
    {
        $caregiver->sponsors()->delete();
        if (! empty($source['sponsor_email_text']) || ! empty($source['sponsor_first_name_text'])) {
            $caregiver->sponsors()->create(['first_name' => $source['sponsor_first_name_text'] ?? null, 'last_name' => $source['sponsor_last_name_text'] ?? null, 'email' => $source['sponsor_email_text'] ?? null]);
        }
    }

    protected function importCertifications(Caregiver $caregiver, array $source): void
    {
        $mappings = ['first_aid_exp_date' => 'First Aid', 'cpr_exp_date' => 'CPR', 'background_check_exp_date' => 'Background Check'];
        foreach ($mappings as $field => $certName) {
            if ($date = $this->timestampToDate($source[$field] ?? null)) {
                if ($type = CertificationType::where('name', $certName)->first()) {
                    $caregiver->certifications()->syncWithoutDetaching([$type->id => ['expiration_date' => $date, 'verified_at' => now()]]);
                }
            }
        }
    }

    protected function importSpecialties(Caregiver $caregiver, array $source): void
    {
        if (! empty($source['baby_specialist_boolean'])) {
            if ($specialty = SpecialtyType::where('name', 'Babies')->first()) {
                $caregiver->specialtyTypes()->syncWithoutDetaching([$specialty->id]);
            }
        }
    }

    protected function importAttributes(Caregiver $caregiver, array $source): void
    {
        if (isset($source['care_com_boolean'])) {
            if ($attribute = AttributeDefinition::where('slug', 'care_com')->first()) {
                $caregiver->attributes()->syncWithoutDetaching([$attribute->id => ['value' => $source['care_com_boolean'] ? 'true' : 'false', 'entity_type' => 'caregiver']]);
            }
        }
    }

    protected function timestampToYear(mixed $v): ?int
    {
        if (! $v) {
            return null;
        }
        if (is_numeric($v)) {
            try {
                return (int) Carbon::createFromTimestampMs($v)->format('Y');
            } catch (\Exception $e) {
                return null;
            }
        }

        return (int) $v;
    }

    protected function timestampToDate(?int $t): ?string
    {
        if (! $t) {
            return null;
        }
        try {
            return Carbon::createFromTimestampMs($t)->toDateString();
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function setupResponseInterceptor(Client $client): void
    {
        $client->executeScript("if (!window._responseInterceptorSetup) { window._capturedResponse = null; window._capturedTimestamp = null; window._responseInterceptorSetup = true; const OriginalXHR = window.XMLHttpRequest; function wrapXHR(xhrInstance) { const originalOpen = xhrInstance.open; const originalSend = xhrInstance.send; xhrInstance.open = function(method, url) { this._interceptedUrl = url; return originalOpen.apply(this, arguments); }; xhrInstance.send = function(body) { const xhr = this; xhr.addEventListener('loadend', function() { if (xhr._interceptedUrl && xhr._interceptedUrl.includes('elasticsearch/search')) { try { const response = JSON.parse(xhr.responseText); window._capturedResponse = response; window._capturedTimestamp = Date.now(); } catch(e) {} } }); return originalSend.apply(this, arguments); }; return xhrInstance; } window.XMLHttpRequest = function() { const xhr = new OriginalXHR(); return wrapXHR(xhr); }; Object.keys(OriginalXHR).forEach(key => { window.XMLHttpRequest[key] = OriginalXHR[key]; }); window.XMLHttpRequest.prototype = OriginalXHR.prototype; const originalFetch = window.fetch; window.fetch = function(...args) { const url = args[0]; if (typeof url === 'string' && url.includes('elasticsearch/search')) { return originalFetch.apply(this, args).then(async function(response) { const clone = response.clone(); try { const data = await clone.json(); window._capturedResponse = data; window._capturedTimestamp = Date.now(); } catch(e) {} return response; }); } return originalFetch.apply(this, args); }; }");
    }

    protected function waitForInterceptedResponse(Client $client, int $timeoutSeconds = 30): ?array
    {
        $startTime = time();
        while (time() - $startTime < $timeoutSeconds) {
            $response = $client->executeScript('return window._capturedResponse;');
            if ($response) {
                return $response;
            }
            usleep(500000);
        }

        return null;
    }

    protected function freePort(int $port): void
    {
        if (PHP_OS_FAMILY === 'Linux') {
            exec("fuser -k {$port}/tcp 2>/dev/null");
        }
    }

    protected function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->removeDirectory("$dir/$file") : unlink("$dir/$file");
        }
        rmdir($dir);
    }

    protected function ensureChromeInstalled(): void
    {
        $chromePaths = ['/usr/bin/google-chrome', '/usr/bin/google-chrome-stable', '/usr/bin/chromium-browser', '/usr/bin/chromium'];
        foreach ($chromePaths as $path) {
            if (file_exists($path) && is_executable($path)) {
                return;
            }
        }
        $this->warn('Chrome not found. Please ensure it is installed.');
    }

    protected function ensureChromeDriverInstalled(): string
    {
        $driversDir = storage_path('app/drivers');
        $chromeDriverPath = $driversDir.'/chromedriver';
        if (file_exists($chromeDriverPath) && is_executable($chromeDriverPath)) {
            return $chromeDriverPath;
        }
        if (! is_dir($driversDir)) {
            @mkdir($driversDir, 0755, true);
        }
        $bdiPath = base_path('vendor/bin/bdi');
        if (file_exists($bdiPath)) {
            exec("{$bdiPath} detect {$driversDir} 2>&1", $output, $returnCode);
            if ($returnCode === 0 && file_exists($chromeDriverPath)) {
                chmod($chromeDriverPath, 0755);

                return $chromeDriverPath;
            }
        }
        throw new \Exception('ChromeDriver not found. Run "vendor/bin/bdi detect storage/app/drivers" manually.');
    }
}
