<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Panther\Client;

#[Signature('import:bubble {type? : The Bubble data type to import (user, jobs, rating, transactions)} {--url= : Custom Bubble URL to scrape} {--limit= : Limit number of records} {--fresh : Force a fresh scrape from Bubble.io, ignoring staged data} {--after= : Only sync records modified after this date (YYYY-MM-DD)} {--before= : Only sync records modified before this date (YYYY-MM-DD)}')]
#[Description('Import data from Bubble.io editor Data tab')]
class ImportBubbleDatabase extends Command
{
    protected const BUBBLE_BASE_URL = 'https://bubble.io/page?id=hello-76539&tab=Data&name=index&subtab=App+data&version=live';

    protected const SUPPORTED_TYPES = ['user', 'jobs', 'rating', 'transactions'];

    protected $syncedCount = 0;

    protected ?\PDO $sqlite = null;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $type = $this->argument('type');
        $limit = (int) $this->option('limit');
        $freshScrape = $this->option('fresh');
        $afterDate = $this->option('after');
        $beforeDate = $this->option('before');

        $typesToProcess = $type ? [$type] : self::SUPPORTED_TYPES;

        $this->initStagingDatabase();

        $client = null;
        $userDataDir = null;

        try {
            foreach ($typesToProcess as $currentType) {
                $this->syncedCount = 0;

                $url = $this->option('url') ?? self::BUBBLE_BASE_URL.'&type_id='.$currentType;
                $stagedCount = $this->getStagedCount($currentType);
                $isComplete = $this->isTypeComplete($currentType);

                $this->info("Starting Bubble sync for type [{$currentType}]");
                $this->line("    [Status] Staged Records: $stagedCount, Fully Synced Before: ".($isComplete ? 'Yes' : 'No'));

                if ($afterDate || $beforeDate) {
                    $this->line('    [Filter] Date Range: '.($afterDate ?: 'ANY').' to '.($beforeDate ?: 'ANY'));
                }

                try {
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

                    $this->scrapeAndSync($client, $currentType, $url, $limit, $constraints);

                    $this->newLine();
                    $this->info("Complete for [{$currentType}]: {$this->syncedCount} records synced to staging");
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
                const isVisible = btn.offsetParent !== null || btn.style.display !== 'none';
                const isDisabled = btn.classList.contains('disabled') || btn.disabled;

                if (isVisible && !isDisabled) {
                    btn.click();
                    return 'clicked';
                }
                return isVisible ? 'disabled' : 'hidden';
            }

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

            $client->executeScript("document.querySelector('.add-constraint')?.click();");
            sleep(1);

            $this->selectBubbleDropdown($client, '.composer-dropdown.bubble-ui.constraint-field', $c['field']);

            $this->selectBubbleDropdown($client, '.composer-dropdown.bubble-ui.operator-field', $c['operator']);

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
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->line("    Attempt {$attempt} to select dropdown: {$searchText}");

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

    protected function scrapeAndSync(Client $client, string $type, string $url, int $limit, array $constraints = []): void
    {
        try {
            $this->info("Loading URL: {$url}");
            $client->request('GET', $url);
            $client->waitFor('body', 30);
            sleep(5);

            $this->info('Dismissing potential popups...');
            $dismissScript = "
                const dismiss = () => {
                    const cancelBtn = document.querySelector('.popup-title')?.parentElement?.querySelector('.btn-cancel');
                    if (cancelBtn) cancelBtn.click();

                    const buttons = document.querySelectorAll('.bubble-element.Button, .btn, .button');
                    const targetTexts = ['SAVE', 'Save', 'Later', 'Not now', 'Ignore', 'Update', 'Upgrade'];

                    for (const b of buttons) {
                        const txt = b.textContent.trim();
                        if (targetTexts.some(t => txt.includes(t))) {
                             if (b.closest('.modal-view') || b.closest('.popup') || b.closest('.overlay')) {
                                b.click();
                             }
                        }
                    }

                    const closeIcons = document.querySelectorAll('.fa-times, .close-icon, .close-button');
                    for (const icon of closeIcons) icon.click();
                };
                dismiss();
            ";
            $client->executeScript($dismissScript);
            sleep(2);

            $this->setupResponseInterceptor($client);

            $this->info('Navigating to App Data tab...');
            $client->executeScript($dismissScript);
            $client->executeScript("const tabs = document.querySelectorAll('.tab-caption'); for (const t of tabs) if (t.textContent.includes('App data')) t.click();");
            sleep(5);

            $this->info("Selecting [{$type}] from the sidebar...");
            $client->executeScript($dismissScript);
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
            sleep(5);

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

                        $stmt = $this->sqlite->prepare('SELECT COUNT(*) FROM staged_records WHERE type = ? AND external_id = ? AND modified_at = ?');
                        $stmt->execute([$type, $externalId, $modifiedAt]);

                        if ((bool) $stmt->fetchColumn()) {
                            $batchPerfectMatches++;
                        } else {
                            $this->upsertStagedRecord($type, $hit);
                            $batchNewOrUpdated++;
                            $this->syncedCount++;
                        }
                    }

                    $this->info("  Results: {$batchNewOrUpdated} New/Updated, {$batchPerfectMatches} Perfect Matches");
                    $totalSynced += count($hits);

                    if (count($hits) < 50 && count($hits) > 0) {
                        $this->info("Reached the absolute end of data for [{$type}] (Batch size ".count($hits).' < 50).');
                        if (empty($constraints)) {
                            $this->markTypeComplete($type);
                        } else {
                            $this->line('    [Note] Completion marker skipped because filters are active.');
                        }
                        break;
                    }

                    if (count($hits) > 0 && $batchNewOrUpdated === 0 && $limit === 0) {
                        if ($isComplete) {
                            $this->info('Fully caught up with local staging (Type is marked complete). Stopping.');
                            break;
                        }

                        if (! $hasFastForwarded && $stagedCount > 50 && empty($constraints)) {
                            $skipCount = floor($stagedCount / 50) - $page;
                            if ($skipCount > 0) {
                                $this->info("Found perfectly synced batch. Triggering automatic fast-forward (skipping {$skipCount} pages)...");

                                $client->executeScript('window._capturedResponse = null;');

                                for ($i = 0; $i < $skipCount; $i++) {
                                    $this->line('  Skipping page '.($page + $i + 1).' of '.($page + $skipCount).'...');
                                    $status = 'not_found';
                                    for ($retry = 0; $retry < 8; $retry++) {
                                        $status = $this->clickLoadMore($client);
                                        if ($status === 'clicked') {
                                            break;
                                        }
                                        usleep(800000);
                                    }

                                    if ($status !== 'clicked' && $status !== 'disabled') {
                                        $this->warn('  Warning: Skip halted at step '.($i + 1)." (Status: $status). Transitioning to capture.");
                                        break;
                                    }

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

                    $loadMoreSuccess = false;
                    for ($retry = 0; $retry < 15; $retry++) {
                        $status = $this->clickLoadMore($client);
                        if ($status === 'clicked') {
                            $loadMoreSuccess = true;
                            break;
                        }

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

    protected function applySorting(Client $client): void
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
