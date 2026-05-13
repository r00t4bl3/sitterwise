<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Enums\ClientType;
use App\Enums\LocationType;
use App\Enums\ServiceType;
use App\Enums\SpecialConsideration;
use App\Models\AttributeDefinition;
use App\Models\Booking;
use App\Models\BookingGroup;
use App\Models\BookingRating;
use App\Models\Caregiver;
use App\Models\CaregiverPayout;
use App\Models\CaregiverStatus;
use App\Models\CertificationType;
use App\Models\Client as ClientModel;
use App\Models\ClientPayment;
use App\Models\Hotel;
use App\Models\SpecialtyType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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
                        $stmt = $this->getStagedRecordsStatement($currentType, $limit ?: null);
                        $this->processStatement($currentType, $stmt, $force, $dryRun);
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
                                    // '--headless=new',
                                    // '--no-sandbox',
                                    // '--disable-dev-shm-usage',
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
            $this->finalizeImport();
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

    protected function finalizeImport(): void
    {
        $this->info('Finalizing import (Calculating summary fields)...');

        $clients = ClientModel::all();
        $this->withProgressBar($clients, function (ClientModel $client) {
            $lastBooking = $client->bookings()->orderBy('start_datetime', 'desc')->first();
            if ($lastBooking) {
                $client->update(['last_booking_date' => $lastBooking->start_datetime]);
            }

            // Link bookings to client's primary address where address_id is null
            $primaryAddress = $client->addresses()->where('is_primary', true)->first();
            if ($primaryAddress) {
                $client->bookings()->whereNull('address_id')->update(['address_id' => $primaryAddress->id]);
            }
        });

        $this->newLine();
        $this->info('Finalization complete.');
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

    protected function getStagedRecordsStatement(string $type, ?int $limit = null): \PDOStatement
    {
        $sql = 'SELECT raw_json FROM staged_records WHERE type = ? ORDER BY modified_at DESC';
        if ($limit) {
            $sql .= " LIMIT $limit";
        }
        $stmt = $this->sqlite->prepare($sql);
        $stmt->execute([$type]);

        return $stmt;
    }

    protected function processStatement(string $type, \PDOStatement $stmt, bool $force, bool $dryRun): void
    {
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $hit = [
                '_source' => json_decode($row['raw_json'], true),
                '_id' => json_decode($row['raw_json'], true)['_id'] ?? null,
            ];
            $this->processHits($type, [$hit], $force, $dryRun);
        }
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
            sleep(1); // Wait for row to appear

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
                }
            ");
            sleep(7);
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
            usleep(500000);
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

            usleep(500000);

            if ($dropdownClicked) {
                $this->info("    Successfully clicked: {$containerSelector}");
            } else {
                $this->warn("    Could not find matching item on attempt {$attempt}");
            }

            usleep(500000);

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

            $this->applySorting($client);

            usleep(500000);

            $this->info('Clicking SAVE button...');
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

    protected function applySorting($client)
    {
        $this->line('  -> Sorting');
        $sortDropdownSelector = '.composer-dropdown.bubble-ui.field';
        $sortDropdownClicked = $client->executeScript("
            const containers = document.querySelectorAll('{$sortDropdownSelector}');
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

        if ($sortDropdownClicked) {
            $this->info("    Successfully clicked: {$sortDropdownSelector}");
        } else {
            $this->warn("    Could not find matching {$sortDropdownSelector}");
        }

        usleep(500000);

        $searchText = 'Modified Date';
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
            $this->warn("    Could not find matching {$searchText}");
        }
    }

    protected function processHits(string $type, array $hits, bool $force, bool $dryRun): void
    {
        foreach ($hits as $index => $hit) {
            $source = $hit['_source'] ?? [];
            $externalId = $hit['_id'] ?? null;

            if ($type === 'user') {
                $name = ($source['first_name_text'] ?? 'Unknown').' '.($source['last_name_text'] ?? '');
                $modifiedDate = $this->timestampToDate($source['Modified Date'] ?? null);
                $this->line("    -> Processing User: $name (Modified: $modifiedDate)");

                if (! $dryRun) {
                    try {
                        DB::transaction(fn () => $this->syncUser($source, $externalId, $force));
                        $this->successCount++;
                    } catch (\Exception $e) {
                        $this->error('       User sync failed: '.$e->getMessage());
                        $this->errorCount++;
                    }
                } else {
                    $this->successCount++;
                }
            } elseif ($type === 'jobs') {
                $clientName = $source['client_first_name_last_name_text'] ?? 'Unknown Client';
                $jobDate = $this->timestampToDate($source['start_date_date'] ?? null);
                $this->line("    -> Processing Job: $clientName on $jobDate");

                if (! $dryRun) {
                    try {
                        DB::transaction(fn () => $this->importJob($source, $externalId, $force));
                        $this->successCount++;
                    } catch (\Exception $e) {
                        $this->error('       Job import failed: '.$e->getMessage());
                        $this->errorCount++;
                    }
                } else {
                    $this->successCount++;
                }
            } elseif ($type === 'rating') {
                $clientName = $source['client_name_text'] ?? 'Unknown Client';
                $this->line("    -> Processing Rating for: $clientName");

                try {
                    DB::transaction(fn () => $this->importRating($source, $externalId, $force, $dryRun));
                    $this->successCount++;
                } catch (\Exception $e) {
                    $this->error('       Rating import failed: '.$e->getMessage());
                    $this->errorCount++;
                }
            } elseif ($type === 'transactions') {
                $this->line("    -> Processing Transaction: $externalId");

                try {
                    DB::transaction(fn () => $this->importTransaction($source, $externalId, $force, $dryRun));
                    $this->successCount++;
                } catch (\Exception $e) {
                    $this->error('       Transaction import failed: '.$e->getMessage());
                    $this->errorCount++;
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

    protected function syncUser(array $source, string $externalId, bool $force): void
    {
        $email = $source['authentication']['email']['email'] ?? null;
        if (! $email) {
            throw new \Exception("User $externalId is missing an email address.");
        }

        // Parse Name with fallbacks
        $names = $this->parseSourceNames($source, $email);
        $firstName = $names['first'];
        $lastName = $names['last'];
        $fullName = trim("$firstName $lastName");

        $role = $source['role_permissions_option_role'] ?? 'caregiver';

        // 1. Find or create the User
        $user = User::where('bubble_id', $externalId)->first();

        if (! $user) {
            // Check for email collision
            $user = User::where('email', $email)->first();
            if ($user) {
                if ($user->bubble_id && $user->bubble_id !== $externalId) {
                    throw new \Exception("Email collision: $email is already linked to Bubble ID {$user->bubble_id}. Record $externalId skipped.");
                }
                // Link existing user to Bubble ID
                $user->update(['bubble_id' => $externalId]);
            } else {
                // Create new user
                $user = User::create([
                    'bubble_id' => $externalId,
                    'email' => $email,
                    'name' => $fullName,
                    'password' => Hash::make($source['temporary_password_text'] ?? 'changeme123'),
                ]);
            }
        }

        $user->update([
            'name' => $fullName,
            'role' => $role,
            'profile_photo_url' => $source['profile_photo_url_text'] ?? null,
        ]);

        // 2. Role-specific sync
        if ($role === 'caregiver' || $role === 'caregiver_applicant') {
            $this->syncCaregiver($user, $source, $force);
        } elseif ($role === 'client') {
            $this->syncClient($user, $source, $force);
        }
    }

    protected function parseSourceNames(array $source, string $email): array
    {
        $first = trim($source['first_name_text'] ?? '');
        $last = trim($source['last_name_text'] ?? '');

        if (! $first && ! empty($source['firstnamelastname_text'])) {
            $parts = explode(' ', trim($source['firstnamelastname_text']), 2);
            $first = $parts[0] ?? '';
            $last = $parts[1] ?? '';
        }

        if (! $first) {
            $first = ucfirst(explode('@', $email)[0]);
            $last = 'User';
        }

        return [
            'first' => $this->formatName($first),
            'last' => $this->formatName($last),
        ];
    }

    protected function formatName(?string $name): string
    {
        if (! $name) {
            return '';
        }

        // Fix all-caps or all-lowercase to Title Case
        return (string) Str::of($name)->trim()->lower()->title();
    }

    protected function formatPhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        // Remove non-numeric characters
        $digits = preg_replace('/\D/', '', $phone);

        // Standardize to (XXX) XXX-XXXX if it's a 10-digit number
        if (strlen($digits) === 10) {
            return '('.substr($digits, 0, 3).') '.substr($digits, 3, 3).'-'.substr($digits, 6);
        }

        // Return as-is for international or short numbers
        return $phone;
    }

    protected function syncCaregiver(User $user, array $source, bool $force): void
    {
        $statusName = $source['cg_status_option_cg_status_options'] ?? 'inactive';
        $status = CaregiverStatus::firstOrCreate(['name' => $statusName]);

        $names = $this->parseSourceNames($source, $user->email);

        $slug = $source['Slug'] ?? null;
        if ($slug) {
            $existingWithSlug = Caregiver::where('slug', $slug)->first();
            if ($existingWithSlug && $existingWithSlug->user_id !== $user->id) {
                // Collision: Append Bubble ID to ensure uniqueness
                $slug = $slug.'-'.substr($user->bubble_id, 0, 5);
            }
        }

        $caregiverData = [
            'user_id' => $user->id,
            'bubble_id' => $user->bubble_id,
            'status_id' => $status->id,
            'first_name' => $names['first'],
            'last_name' => $names['last'],
            'slug' => $slug,
            'phone' => $this->formatPhone($source['phone_text'] ?? null),
            'address' => $source['address_geographic_address']['address'] ?? null,
            'date_of_birth' => $this->timestampToDate($source['date_of_birth_date'] ?? null),
            'biography' => $source['bio_text'] ?? null,
            'notes' => $source['internal_notes_text'] ?? null,
            'education_level' => $source['highest_level_education_text'] ?? null,
            'languages' => $source['languages_text'] ?? null,
            'stripe_account_id' => $source['cg_stripe_id_text'] ?? $source['stripe_account_id_text'] ?? null,
            'rating' => ($source['cg_star_rating__rated_by_client__number'] ?? 0) > 0 ? $source['cg_star_rating__rated_by_client__number'] : null,
            'admin_rating' => ! empty($source['5_star_boolean']) ? 5.0 : null,
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

    protected function syncClient(User $user, array $source, bool $force): void
    {
        $names = $this->parseSourceNames($source, $user->email);

        $clientData = [
            'user_id' => $user->id,
            'bubble_id' => $user->bubble_id,
            'first_name' => $names['first'],
            'last_name' => $names['last'],
            'biography' => $source['bio_text'] ?? null,
            'phone' => $this->formatPhone($source['phone_text'] ?? 'N/A'), // phone is NOT NULL in DB
            'client_type' => $this->mapClientType($source),
            'how_did_you_hear' => $source['how_did_you_hear_about_us_text'] ?? null,
            'special_needs_notes' => $source['internal_notes_text'] ?? null,
            'stripe_customer_id' => $source['StripeCustomerID'] ?? null,
        ];

        $client = ClientModel::updateOrCreate(['user_id' => $user->id], $clientData);

        $this->syncClientAddresses($client, $source);
        $this->syncClientChildren($client, $source['names_and_ages_of_kids_text'] ?? null);
        $this->syncClientPets($client, $source['pets_in_the_home_text'] ?? null);
        $this->syncClientCaregiverList($client, 'favorite', $source['favorite_caregivers_list_text'] ?? null);
        $this->syncClientCaregiverList($client, 'blocked', $source['do_not_use_list_text'] ?? null);
    }

    protected function mapClientType(array $source): string
    {
        if (! empty($source['corporate__boolean'])) {
            return ClientType::Invoiced->value;
        }

        if (! empty($source['address_is_hotel__boolean'])) {
            return ClientType::Vacationer->value;
        }

        return ClientType::Resident->value;
    }

    protected function mapServiceType(?string $bubbleService): string
    {
        return match (strtolower($bubbleService ?? '')) {
            'corporate__invoiced_' => ServiceType::CorporateInvoiced->value,
            'group_childcare' => ServiceType::GroupChildcareInvoiced->value,
            'petsitting' => ServiceType::Petsitter->value,
            'comped' => ServiceType::Comped->value,
            'companion_care' => ServiceType::CompanionCare->value,
            default => ServiceType::Babysitter->value,
        };
    }

    protected function mapLocationType(?string $hotelOption): string
    {
        if (! $hotelOption || strtolower($hotelOption) === 'no') {
            return LocationType::PrivateHome->value;
        }

        return LocationType::Hotel->value;
    }

    protected function syncClientAddresses(ClientModel $client, array $source): void
    {
        $locationType = match ($client->client_type) {
            ClientType::Vacationer->value => LocationType::Hotel->value,
            ClientType::Invoiced->value => LocationType::EventVenue->value,
            default => LocationType::PrivateHome->value,
        };
        $label = match ($client->client_type) {
            ClientType::Vacationer->value => LocationType::Hotel->label(),
            ClientType::Invoiced->value => LocationType::EventVenue->label(),
            default => LocationType::PrivateHome->label(),
        };

        $geo = $source['address_geographic_address'] ?? null;
        if ($geo && ! empty($geo['components'])) {
            $c = $geo['components'];
            $client->addresses()->updateOrCreate(
                ['label' => $label, 'is_primary' => true],
                [
                    'location_type' => $locationType,
                    'line1' => trim(($c['street number'] ?? '').' '.($c['street'] ?? '')),
                    'city' => $c['city'] ?? 'Unknown',
                    'state' => $c['state code'] ?? 'Unknown',
                    'zip' => $c['zip code'] ?? '00000',
                ]
            );
        }

        // Only create alternate address if it differs from primary
        $homeGeo = $source['home_address_geographic_address'] ?? null;
        if ($homeGeo && ! empty($homeGeo['components'])) {
            $primary = $client->addresses()->where('is_primary', true)->first();
            $homeLine1 = trim(($homeGeo['components']['street number'] ?? '').' '.($homeGeo['components']['street'] ?? ''));
            if (! $primary || $primary->line1 !== $homeLine1) {
                $c = $homeGeo['components'];
                $client->addresses()->updateOrCreate(
                    ['label' => $label, 'is_primary' => false],
                    [
                        'location_type' => $locationType,
                        'line1' => $homeLine1,
                        'city' => $c['city'] ?? 'Unknown',
                        'state' => $c['state code'] ?? 'Unknown',
                        'zip' => $c['zip code'] ?? '00000',
                    ]
                );
            }
        }

        $addrBook = $source['address_book_list_text'] ?? [];
        if (is_array($addrBook)) {
            foreach ($addrBook as $addr) {
                $addr = trim((string) $addr);
                if (! $addr) {
                    continue;
                }

                $parts = array_map('trim', explode(',', $addr));
                $line1 = $parts[0] ?? $addr;
                $city = $parts[1] ?? null;
                $state = null;
                $zip = null;
                if (count($parts) >= 3) {
                    $stateZip = explode(' ', trim($parts[count($parts) - 2]));
                    $state = $stateZip[0] ?? null;
                    $zip = $stateZip[1] ?? null;
                }

                $client->addresses()->firstOrCreate(
                    ['line1' => $line1],
                    [
                        'label' => $label,
                        'is_primary' => false,
                        'location_type' => $locationType,
                        'city' => $city ?? 'Unknown',
                        'state' => $state ?? 'Unknown',
                        'zip' => $zip ?? '00000',
                    ]
                );
            }
        }
    }

    protected function syncClientCaregiverList(ClientModel $client, string $type, $list): void
    {
        if (is_string($list)) {
            $list = array_map('trim', explode(',', $list));
        }

        if (! is_array($list) || empty($list)) {
            return;
        }

        $caregiverIds = [];
        foreach ($list as $fullName) {
            $fullName = trim((string) $fullName);
            if (! $fullName) {
                continue;
            }

            $parts = explode(' ', $fullName, 2);
            $firstName = $parts[0] ?? '';
            $lastName = $parts[1] ?? '';

            $caregiver = Caregiver::where('first_name', $firstName)
                ->where('last_name', $lastName)
                ->first();

            if ($caregiver) {
                $caregiverIds[] = $caregiver->id;
            } else {
                $this->warn("       {$type} caregiver not found by name: {$fullName}");
            }
        }

        if (! empty($caregiverIds)) {
            if ($type === 'favorite') {
                $client->favoriteCaregivers()->sync($caregiverIds);
            } else {
                $client->blockedCaregivers()->sync($caregiverIds);
            }
        }
    }

    protected function syncClientChildren(ClientModel $client, ?string $text): void
    {
        if (! $text) {
            return;
        }

        $names = preg_split('/[,;]/', $text);
        foreach ($names as $name) {
            $name = trim($name);
            if (! $name) {
                continue;
            }

            $client->children()->updateOrCreate(
                ['name' => $name],
                []
            );
        }
    }

    protected function syncClientPets(ClientModel $client, ?string $text): void
    {
        if (! $text) {
            return;
        }

        $pets = [];
        $lower = strtolower($text);
        if (str_contains($lower, 'dog')) {
            $pets[] = 'dog';
        }
        if (str_contains($lower, 'cat')) {
            $pets[] = 'cat';
        }

        if (empty($pets)) {
            $client->pets()->updateOrCreate(['name' => 'Other', 'type' => 'other', 'notes' => $text]);

            return;
        }

        foreach ($pets as $type) {
            $client->pets()->updateOrCreate(
                ['type' => $type],
                ['name' => ucfirst($type), 'notes' => $text]
            );
        }
    }

    protected function importJob(array $source, string $externalId, bool $force): void
    {
        $clientEmail = $source['client_email_text'] ?? null;
        $cgEmail = $source['cg_email_text'] ?? null;

        $client = null;
        if ($clientEmail) {
            $client = ClientModel::whereHas('user', fn ($q) => $q->where('email', $clientEmail))->first();
        }

        $caregiver = null;
        if ($cgEmail) {
            $caregiver = Caregiver::whereHas('user', fn ($q) => $q->where('email', $cgEmail))->first();
        }

        $statusMapping = [
            'paid' => BookingStatus::Paid,
            'completed' => BookingStatus::Completed,
            'confirmed' => BookingStatus::Confirmed,
            'pending' => BookingStatus::Pending,
            'received' => BookingStatus::Received,
            'cancelled' => BookingStatus::Cancelled,
        ];

        $bubbleStatus = strtolower($source['job_status_option_job_status'] ?? 'received');
        $status = $statusMapping[$bubbleStatus] ?? BookingStatus::Received;

        // Map Address
        $geo = $source['street_address_geographic_address'] ?? [];
        $components = $geo['components'] ?? [];

        $bookingData = [
            'bubble_id' => $externalId,
            'client_id' => $client?->id,
            'caregiver_id' => $caregiver?->id,
            'start_datetime' => $this->timestampToDateTime($source['start_date_date'] ?? null),
            'end_datetime' => $this->timestampToDateTime($source['end_date_date'] ?? null),
            'status' => $status->value,
            'service_type' => $this->mapServiceType($source['service1_option_services'] ?? 'babysitting'),
            'location_type' => $this->mapLocationType($source['address_is_hotel__option_list_of_hotels'] ?? ''),
            'address_line1' => trim(($components['street number'] ?? '').' '.($components['street'] ?? '')),
            'address_city' => $components['city'] ?? null,
            'address_state' => $components['state code'] ?? null,
            'address_zip' => $components['zip code'] ?? null,
            'total_working_hour' => $source['total_hours_number'] ?? 0,
            'charge_to_client_hourly' => ($source['client_job_hourly_rate_number'] ?? 0) * 100,
            'paid_to_caregiver_hourly' => ($source['job_cg_hourly_rate_number'] ?? 0) * 100,
            'sitterwise_cut_hourly' => ($source['job_agency_hourly_rate_number'] ?? 0) * 100,
            'charge_to_client' => ($source['client_total_number'] ?? 0) * 100,
            'paid_to_caregiver' => ($source['caregiver_total_number'] ?? 0) * 100,
            'sitterwise_cut' => ($source['sw_total_number'] ?? 0) * 100,
            'tip' => ($source['cg_tip_number'] ?? 0) * 100,
            'bonus' => ($source['bonus_number'] ?? 0) * 100,
            'reimbursement' => ($source['check_out_reimbursement_number'] ?? 0) * 100,
            'reimbursement_description' => $source['check_out_reimbursement_description_text'] ?? null,
            'hotel_fee' => ($source['job_hotel_booking_fee_number'] ?? 0) * 100,
            'hotel_id' => $this->findHotelId($source['hotel_name_text'] ?? null, $source['address_is_hotel__option_list_of_hotels'] ?? null),
            'client_first_name' => $this->formatName($source['client_first_name1_text'] ?? null),
            'client_last_name' => $this->formatName($source['client_last_name1_text'] ?? null),
            'client_email' => $clientEmail,
            'client_phone' => $this->formatPhone($source['client_phone_text'] ?? null),
            'caregiver_notes' => $source['cg_checkout_job_notes_text'] ?? $source['caregiver_notes_text'] ?? null,
            'notes_to_sitterwise' => $source['notes_to_sw_admin_text'] ?? null,
            'admin_notes' => $source['admin_notes_text'] ?? null,
            'payment_status' => $bubbleStatus === 'paid' ? 'paid' : 'unpaid',
            'stripe_payment_intent_id' => $source['payment_intent_id_text'] ?? null,
            'cancelled_at' => $this->timestampToDateTime($source['cancellation_date_date'] ?? null),
            'cancellation_reason' => $source['cancellation_reason_text'] ?? null,
            'children' => $this->parseChildren($source['names_and_ages_of_children_text'] ?? null, $source['__of_children_option_number_of_kids'] ?? null),
            'pets' => $this->parsePets($source['pets_text'] ?? null),
            'special_considerations' => $this->mapSpecialConsiderations($source),
        ];

        // 1. Update Client Bio with House Notes
        if ($client && ! empty($source['house_notes_text'])) {
            $currentBio = $client->biography ?? '';
            $houseNotes = $source['house_notes_text'];
            if (! str_contains($currentBio, $houseNotes)) {
                $client->update(['biography' => trim($currentBio."\n\nHouse Notes: ".$houseNotes)]);
            }
        }

        // 2. Update Caregiver Stripe ID if missing
        if ($caregiver && ! empty($source['cg_stripe_id_text'])) {
            if (empty($caregiver->stripe_account_id)) {
                $caregiver->update(['stripe_account_id' => $source['cg_stripe_id_text']]);
            }
        }

        if ($client) {
            $group = $client->bookingGroups()->firstOrCreate(
                ['submission_type' => 'import'],
                [
                    'submitted_at' => now(),
                ]
            );
            $bookingData['booking_group_id'] = $group->id;
        } else {
            $group = BookingGroup::firstOrCreate(
                ['submission_type' => 'import'],
                [
                    'client_id' => ClientModel::first()?->id ?? 1,
                    'submitted_at' => now(),
                ]
            );
            $bookingData['booking_group_id'] = $group->id;
            $bookingData['client_id'] = $group->client_id;
        }

        Booking::updateOrCreate(['bubble_id' => $externalId], $bookingData);
    }

    protected function findHotelId(?string $hotelName, ?string $bubbleSlug): ?int
    {
        // Only match when hotel_name_text is present — slugs (snake_case) don't resemble hotel names
        if (! $hotelName || in_array(strtolower($hotelName), ['no', 'other', '', 'none'], true)) {
            return null;
        }

        $normalized = $this->normalizeHotelName($hotelName);

        $hotels = Hotel::all();

        // 1. Exact normalized match
        foreach ($hotels as $hotel) {
            if ($this->normalizeHotelName($hotel->name) === $normalized) {
                return $hotel->id;
            }
        }

        // 2. Levenshtein distance <= 2 (catches typos like "mariott" -> "marriott")
        foreach ($hotels as $hotel) {
            if (levenshtein($normalized, $this->normalizeHotelName($hotel->name)) <= 2) {
                return $hotel->id;
            }
        }

        // 3. Contains check (catches truncated/wordy names)
        foreach ($hotels as $hotel) {
            $hotelNorm = $this->normalizeHotelName($hotel->name);
            if (str_contains($hotelNorm, $normalized) || str_contains($normalized, $hotelNorm)) {
                return $hotel->id;
            }
        }

        $this->warn("       No hotel match found for: {$name}");

        return null;
    }

    protected function normalizeHotelName(string $name): string
    {
        $name = strtolower(trim($name));
        $name = str_replace([' - ', ' & ', ' and ', ' by ', ' the ', ' at '], ' ', $name);
        $name = str_replace(['-', ',', '.', '’', "'"], '', $name);
        $name = preg_replace('/\b(the|hotel|resort|spa|inn|suites)\b/', '', $name);
        $name = preg_replace('/\s+/', ' ', $name);

        return trim($name);
    }

    protected function parseChildren(?string $text, ?string $countStr): ?array
    {
        if (! $text && ! $countStr) {
            return null;
        }

        $children = [];
        $count = (int) str_replace('_', '', $countStr ?? '1');

        if ($text) {
            // Very basic heuristic split
            $parts = preg_split('/[,;]/', $text);
            foreach ($parts as $part) {
                if (trim($part)) {
                    $children[] = ['name' => trim($part)];
                }
            }
        }

        // Ensure we have at least the count from Bubble
        while (count($children) < $count) {
            $children[] = ['name' => 'Child '.(count($children) + 1)];
        }

        return $children;
    }

    protected function parsePets(?string $text): ?array
    {
        if (! $text) {
            return null;
        }

        $pets = [];
        $lower = strtolower($text);
        if (str_contains($lower, 'dog')) {
            $pets[] = ['type' => 'dog', 'notes' => $text];
        }
        if (str_contains($lower, 'cat')) {
            $pets[] = ['type' => 'cat', 'notes' => $text];
        }

        return empty($pets) ? [['type' => 'other', 'notes' => $text]] : $pets;
    }

    protected function mapSpecialConsiderations(array $source): array
    {
        $considerations = [];
        $list = $source['special_considerations__new__list_option_special_considerations'] ?? [];

        foreach ($list as $bubbleVal) {
            if (str_contains($bubbleVal, 'infant_care')) {
                $considerations[] = SpecialConsideration::InfantCare->value;
            }
            if (str_contains($bubbleVal, 'special_needs')) {
                $considerations[] = SpecialConsideration::SpecialNeedsCare->value;
            }
            if (str_contains($bubbleVal, 'swimming')) {
                $considerations[] = SpecialConsideration::SwimmingRequested->value;
            }
            if (str_contains($bubbleVal, 'parent_will_be_present')) {
                $considerations[] = SpecialConsideration::ParentWillBePresent->value;
            }
        }

        // Heuristic from notes/text
        $notes = strtolower(($source['special_considerations1_text'] ?? '').' '.($source['special_considerations_text'] ?? ''));
        if (str_contains($notes, 'infant')) {
            $considerations[] = SpecialConsideration::InfantCare->value;
        }

        // From pets
        $pets = $this->parsePets($source['pets_text'] ?? null);
        foreach ($pets ?? [] as $pet) {
            if ($pet['type'] === 'dog') {
                $considerations[] = SpecialConsideration::FamilyHasDogsOnsite->value;
            }
            if ($pet['type'] === 'cat') {
                $considerations[] = SpecialConsideration::FamilyHasCatsOnsite->value;
            }
        }

        return array_values(array_unique($considerations));
    }

    protected function timestampToDateTime(?int $t): ?string
    {
        if (! $t) {
            return null;
        }
        try {
            return Carbon::createFromTimestampMs($t, 'America/Los_Angeles')->toDateTimeString();
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function importRating(array $source, string $externalId, bool $force, bool $dryRun = false): void
    {
        $clientEmail = $source['client_email_text'] ?? null;
        $cgEmail = $source['cg_email_text'] ?? null;
        $date = $this->timestampToDateTime($source['date_date'] ?? null);

        if (! $clientEmail || ! $date) {
            throw new \Exception("Rating $externalId is missing critical lookup data (email or date).");
        }

        // Find the booking
        $booking = Booking::whereHas('client.user', fn ($q) => $q->where('email', $clientEmail))
            ->whereBetween('start_datetime', [
                Carbon::parse($date)->subMinutes(5),
                Carbon::parse($date)->addMinutes(5),
            ]);

        if ($cgEmail) {
            $booking->whereHas('caregiver.user', fn ($q) => $q->where('email', $cgEmail));
        }

        $booking = $booking->first();

        if (! $booking) {
            // Fallback: Find MOST RECENT booking for this client BEFORE this date
            $booking = Booking::whereHas('client.user', fn ($q) => $q->where('email', $clientEmail))
                ->where('start_datetime', '<=', $date)
                ->orderBy('start_datetime', 'desc')
                ->first();
        }

        if (! $booking) {
            throw new \Exception("Could not find a booking for $clientEmail around $date.");
        }

        $ratingValue = $source['number_number'] ?? 0;
        if ($ratingValue <= 0) {
            // Confirmation: 0-star ratings are confirmed as placeholder values, not genuine scores.
            $this->line("       [Info] Skipping 0-star placeholder rating $externalId.");

            return;
        }

        $comment = $source['feedback_notes_text'] ?? null;

        $raterId = null;
        $ratableId = null;
        $ratableType = null;

        if (! empty($source['review_for_client_boolean'])) {
            // Caregiver rating the Client
            $raterId = $booking->caregiver?->user_id;
            $ratableId = $booking->client_id;
            $ratableType = ClientModel::class;
        } else {
            // Client rating the Caregiver (Default)
            $raterId = $booking->client?->user_id;
            $ratableId = $booking->caregiver_id;
            $ratableType = Caregiver::class;
        }

        if (! $raterId || ! $ratableId) {
            throw new \Exception("Could not determine rater/ratable (Missing User/ID). Booking #{$booking->id}");
        }

        if ($dryRun) {
            $this->line("       [Dry-Run] Would link rating to Booking #{$booking->id} (Rater: $raterId -> Ratable: $ratableId)");

            return;
        }

        BookingRating::updateOrCreate(
            [
                'booking_id' => $booking->id,
                'rater_id' => $raterId,
                'ratable_id' => $ratableId,
                'ratable_type' => $ratableType,
            ],
            [
                'bubble_id' => $externalId,
                'rating' => $ratingValue,
                'comment' => $comment,
            ]
        );
    }

    protected function importTransaction(array $source, string $externalId, bool $force, bool $dryRun = false): void
    {
        $pi = $source['payment_intent_id_text'] ?? null;
        $clientStripeId = $source['client_stripe_id_text'] ?? null;
        $caregiverStripeId = $source['caregiver_stripe_id_text'] ?? null;
        $date = $this->timestampToDateTime($source['date_date'] ?? $source['Created Date'] ?? null);

        // Keep amounts in cents as received from Bubble/Stripe
        $amount = $source['amount_number'] ?? 0;
        $payoutAmount = $source['caregiver_total_transfer_number'] ?? 0;

        $pi = $source['payment_intent_id_text'] ?? null;
        // 1. Find Booking
        $booking = null;
        if ($pi) {
            $booking = Booking::where('stripe_payment_intent_id', $pi)->first();
        }

        if (! $booking) {
            // Fallback: Find by Client/Caregiver and Date
            $query = Booking::query();

            // Only use Client ID if it looks like a Stripe Customer ID
            if ($clientStripeId && str_starts_with($clientStripeId, 'cus_')) {
                $query->whereHas('client', fn ($q) => $q->where('stripe_customer_id', $clientStripeId));
            }

            if ($caregiverStripeId) {
                $query->whereHas('caregiver', fn ($q) => $q->where('stripe_account_id', $caregiverStripeId));
            }

            // Look for a booking starting within 3 days of transaction
            $booking = (clone $query)->whereBetween('start_datetime', [
                Carbon::parse($date)->subDays(3),
                Carbon::parse($date)->addDays(3),
            ])->orderBy('start_datetime', 'desc')->first();

            // Extreme Fallback: Match by amount if caregiver and date match
            if (! $booking && $amount > 0) {
                $booking = (clone $query)->where('charge_to_client', $amount)
                    ->whereBetween('start_datetime', [
                        Carbon::parse($date)->subDays(7),
                        Carbon::parse($date)->addDays(7),
                    ])->first();
            }
        }

        if ($dryRun) {
            $this->line("       [Dry-Run] Would import Transaction $externalId ($amount) for Booking #".($booking?->id ?? 'NOT FOUND'));

            return;
        }

        if ($booking) {
            // Client Payment
            ClientPayment::updateOrCreate(
                ['bubble_id' => $externalId],
                [
                    'booking_id' => $booking->id,
                    'client_id' => $booking->client_id,
                    'amount' => $amount,
                    'status' => 'succeeded',
                    'provider' => 'stripe',
                    'provider_payment_id' => $pi,
                    'paid_at' => $date,
                ]
            );

            // Caregiver Payout
            if ($payoutAmount > 0 && $booking->caregiver) {
                // Ensure a payout method exists
                $method = $booking->caregiver->payoutMethods()->updateOrCreate(
                    ['provider' => 'stripe'],
                    [
                        'provider_method_id' => $booking->caregiver->stripe_account_id ?? 'imported_from_bubble',
                        'account_type' => 'unknown',
                        'bank_name' => 'Imported from Bubble',
                        'last4' => '0000',
                    ]
                );

                CaregiverPayout::updateOrCreate(
                    ['bubble_id' => $externalId],
                    [
                        'booking_id' => $booking->id,
                        'caregiver_id' => $booking->caregiver_id,
                        'caregiver_payout_method_id' => $method->id,
                        'amount' => $payoutAmount,
                        'status' => 'paid',
                        'payout_date' => $date,
                    ]
                );
            }
        } else {
            $this->warn("       Warning: No booking found for Transaction $externalId. Skipping financial records.");
        }
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
                return (int) Carbon::createFromTimestampMs($v, 'America/Los_Angeles')->format('Y');
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
            return Carbon::createFromTimestampMs($t, 'America/Los_Angeles')->toDateString();
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
