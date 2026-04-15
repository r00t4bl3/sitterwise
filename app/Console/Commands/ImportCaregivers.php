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
use Illuminate\Support\Facades\Http;
use Symfony\Component\Panther\Client;

#[Signature('caregivers:import {file? : Path to JSON file} {--bubble : Scrape from Bubble URL} {--bubble-url= : Custom Bubble URL} {--force : Overwrite existing records} {--dry-run : Preview without saving} {--limit= : Limit number of records} {--no-transaction : Disable transaction}')]
#[Description('Import caregivers from JSON file or Bubble URL')]
class ImportCaregivers extends Command
{
    protected $successCount = 0;

    protected $errorCount = 0;

    protected $errors = [];

    protected $lastScrapedData = [];

    // protected const DEFAULT_BUBBLE_URL = 'https://bubble.io/page?id=hello-76539&tab=Data&name=index&type_id=user&view_id=1722285485908x793863589007268200';
    protected const DEFAULT_BUBBLE_URL = 'https://bubble.io/page?id=hello-76539&tab=Data&name=index&subtab=Data+Types&version=live&view_id=1726242294228x588073704353407600&type_id=user';

    protected const BUBBLE_API_URL = 'https://bubble.io/elasticsearch/search';

    public function handle(): int
    {
        $file = $this->argument('file');
        $useBubble = $this->option('bubble');
        $bubbleUrl = $this->option('bubble-url') ?? self::DEFAULT_BUBBLE_URL;
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');
        $limit = $this->option('limit');
        $useTransaction = ! $this->option('no-transaction');

        // Determine data source
        if ($useBubble) {
            $this->info('Scraping data from Bubble URL...');
            $hits = $this->scrapeBubbleData($bubbleUrl, $limit);
        } else {
            if (! $file) {
                $this->error('Please provide either a JSON file argument or use --bubble option.');

                return Command::FAILURE;
            }

            if (! file_exists($file)) {
                $this->error("File not found: {$file}");

                return Command::FAILURE;
            }

            $json = file_get_contents($file);
            $data = json_decode($json, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error('Invalid JSON file: '.json_last_error_msg());

                return Command::FAILURE;
            }

            $hits = $data['hits']['hits'] ?? $data['hits'] ?? [];
            if ($limit) {
                $hits = array_slice($hits, 0, (int) $limit);
            }
        }

        $this->info('Found '.count($hits).' caregivers to import.');
        $this->info('Transaction: '.($useTransaction ? 'enabled' : 'disabled'));
        $this->info('Dry run: '.($dryRun ? 'yes' : 'no'));

        if ($dryRun) {
            $this->warn('Running in dry-run mode - no data will be saved.');
        }

        $this->newLine();

        foreach ($hits as $index => $source) {
            $sourceData = $source['_source'] ?? [];
            $externalId = $sourceData['_id'] ?? null;

            $this->line('Processing ['.($index + 1).'/'.count($hits).']: '.($sourceData['first_name_text'] ?? 'Unknown').' '.($sourceData['last_name_text'] ?? ''));

            $validation = $this->validateRecord($sourceData);
            if (! $validation['valid']) {
                $this->error('  Validation failed: '.implode(', ', $validation['errors']));
                $this->errors[] = [
                    'external_id' => $externalId,
                    'name' => ($sourceData['first_name_text'] ?? 'Unknown').' '.($sourceData['last_name_text'] ?? ''),
                    'errors' => $validation['errors'],
                ];
                $this->errorCount++;

                continue;
            }

            if (! $dryRun) {
                try {
                    if ($useTransaction) {
                        DB::transaction(function () use ($sourceData, $force) {
                            $this->importCaregiver($sourceData, $force);
                        });
                    } else {
                        $this->importCaregiver($sourceData, $force);
                    }
                    $this->successCount++;
                } catch (\Throwable $e) {
                    $this->error('  Import failed: '.$e->getMessage());
                    $this->errors[] = [
                        'external_id' => $externalId,
                        'name' => ($sourceData['first_name_text'] ?? 'Unknown').' '.($sourceData['last_name_text'] ?? ''),
                        'errors' => [$e->getMessage()],
                    ];
                    $this->errorCount++;
                }
            } else {
                $this->successCount++;
            }
        }

        $this->newLine();
        $this->info('Import complete:');
        $this->line("  Success: {$this->successCount}");
        $this->line("  Errors: {$this->errorCount}");

        if ($this->errors && ! $dryRun) {
            $this->newLine();
            $this->warn('Errors:');
            foreach ($this->errors as $error) {
                $this->line("  - {$error['name']} ({$error['external_id']}): ".implode(', ', $error['errors']));
            }
        }

        return $this->errorCount > 0 ? Command::FAILURE : Command::SUCCESS;
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

        $firstName = $source['first_name_text'] ?? null;
        $lastName = $source['last_name_text'] ?? null;
        if (! $firstName) {
            $errors[] = 'Missing first name';
        }
        if (! $lastName) {
            $errors[] = 'Missing last name';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    protected function importCaregiver(array $source, bool $force): void
    {
        $email = $source['authentication']['email']['email'];
        $externalId = $source['_id'] ?? null;
        $firstName = $source['first_name_text'] ?? '';
        $lastName = $source['last_name_text'] ?? '';
        $role = $source['role_permissions_option_role'] ?? 'caregiver';

        $existingUser = User::where('email', $email)->first();
        if ($existingUser) {
            if (! $force) {
                throw new \Exception("User with email {$email} already exists (use --force to overwrite)");
            }
            $user = $existingUser;
        } else {
            $user = User::create([
                'name' => "{$firstName} {$lastName}",
                'email' => $email,
                'password' => Hash::make($source['temporary_password_text'] ?? 'changeme123'),
                'role' => $role,
                'profile_photo_url' => $source['profile_photo_url_text'] ?? null,
            ]);
        }

        $statusName = $source['cg_status_option_cg_status_options'] ?? 'inactive';
        $status = CaregiverStatus::where('name', 'like', $statusName)->first();
        if (! $status) {
            $status = CaregiverStatus::where('name', 'like', 'inactive')->first();
        }

        $metadata = [
            'external_id' => $externalId,
            'application_availability' => $source['application__availability_text'] ?? null,
            'drink' => $source['drink__text'] ?? null,
            'tattoos' => $source['tattoos__text'] ?? null,
            'felony' => $source['felony__text'] ?? null,
            'drug_abuse' => $source['alcohol_or_drug_abuse___text'] ?? null,
        ];
        $metadata = array_filter($metadata, fn ($v) => $v !== null);

        $languages = null;
        if (! empty($source['languages_text'])) {
            $languages = array_map('trim', explode(',', $source['languages_text']));
        }

        $caregiverData = [
            'user_id' => $user->id,
            'status_id' => $status?->id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'slug' => $source['Slug'] ?? null,
            'phone' => $source['phone_text'] ?? null,
            'address' => $source['address_geographic_address']['address'] ?? null,
            'date_of_birth' => $this->timestampToDate($source['date_of_birth_date'] ?? null),
            'rating' => $source['cg_star_rating__rated_by_client__number'] ?? null,
            'biography' => $source['bio_text'] ?? null,
            'notes' => $source['internal_notes_text'] ?? null,
            'stripe_account_id' => $source['stripe_account_id_text'] ?? null,
            'stripe_charges_enabled' => $source['charges_enabled_boolean'] ?? null,
            'education_level' => $source['highest_level_education_text'] ?? null,
            'languages' => $languages ? json_encode($languages) : null,
            'metadata' => $metadata ? json_encode($metadata) : null,
        ];

        if ($existingUser && $force) {
            $caregiver = Caregiver::where('user_id', $user->id)->first();
            if ($caregiver) {
                $caregiver->educations()->delete();
                $caregiver->experiences()->delete();
                $caregiver->references()->delete();
                $caregiver->sponsors()->delete();
                $caregiver->certifications()->detach();
                $caregiver->specialtyTypes()->detach();
                $caregiver->attributes()->detach();
                $caregiver->update(array_filter($caregiverData));
            } else {
                $caregiver = Caregiver::create($caregiverData);
            }
        } elseif ($existingUser) {
            throw new \Exception("User with email {$email} already exists (use --force to overwrite)");
        } else {
            $caregiver = Caregiver::create($caregiverData);
        }

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
        if (! empty($source['high_school_name_text'])) {
            $caregiver->educations()->create([
                'education_type' => 'high_school',
                'school_name' => $source['high_school_name_text'],
                'graduation_year' => $this->timestampToYear($source['graduation_year_date'] ?? null),
            ]);
        }

        if (! empty($source['college_name_text'])) {
            $caregiver->educations()->create([
                'education_type' => 'college',
                'school_name' => $source['college_name_text'],
                'graduation_year' => $this->timestampToYear($source['college_graduation_year_text'] ?? null),
            ]);
        }
    }

    protected function importExperiences(Caregiver $caregiver, array $source): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $startKey = "childcare_experience_{$i}_start_date_date";
            $endKey = "childcare_experience_{$i}_end_date_date";
            $detailsKey = "childcare_experience_{$i}_details_text";

            if (! empty($source[$startKey]) || ! empty($source[$detailsKey])) {
                $caregiver->experiences()->create([
                    'sequence' => $i,
                    'start_date' => $this->timestampToDate($source[$startKey] ?? null),
                    'end_date' => $this->timestampToDate($source[$endKey] ?? null),
                    'details' => $source[$detailsKey] ?? null,
                ]);
            }
        }
    }

    protected function importReferences(Caregiver $caregiver, array $source): void
    {
        $references = $source['previous_caregivers_list_text'] ?? [];
        if (is_string($references)) {
            $references = array_map('trim', explode(',', $references));
        }
        if (! is_array($references)) {
            $references = [];
        }

        foreach ($references as $referenceName) {
            if (! empty(trim($referenceName))) {
                $caregiver->references()->create([
                    'reference_name' => trim($referenceName),
                ]);
            }
        }
    }

    protected function importSponsors(Caregiver $caregiver, array $source): void
    {
        if (! empty($source['sponsor_email_text']) || ! empty($source['sponsor_first_name_text'])) {
            $caregiver->sponsors()->create([
                'first_name' => $source['sponsor_first_name_text'] ?? null,
                'last_name' => $source['sponsor_last_name_text'] ?? null,
                'email' => $source['sponsor_email_text'] ?? null,
            ]);
        }
    }

    protected function importCertifications(Caregiver $caregiver, array $source): void
    {
        $certificationMappings = [
            'first_aid_exp_date' => 'First Aid',
            'cpr_exp_date' => 'CPR',
            'background_check_exp_date' => 'Background Check',
        ];

        foreach ($certificationMappings as $dateField => $certName) {
            $expirationDate = $this->timestampToDate($source[$dateField] ?? null);
            if ($expirationDate) {
                $certType = CertificationType::where('name', $certName)->first();
                if ($certType) {
                    $caregiver->certifications()->syncWithoutDetaching([
                        $certType->id => [
                            'expiration_date' => $expirationDate,
                            'verified_at' => now(),
                        ],
                    ]);
                }
            }
        }
    }

    protected function importSpecialties(Caregiver $caregiver, array $source): void
    {
        if (! empty($source['baby_specialist_boolean'])) {
            $specialty = SpecialtyType::where('name', 'Babies')->first();
            if ($specialty) {
                $caregiver->specialtyTypes()->syncWithoutDetaching([$specialty->id]);
            }
        }
    }

    protected function importAttributes(Caregiver $caregiver, array $source): void
    {
        if (isset($source['care_com_boolean'])) {
            $attribute = AttributeDefinition::where('slug', 'care_com')->first();
            if ($attribute) {
                $caregiver->attributes()->syncWithoutDetaching([
                    $attribute->id => [
                        'value' => $source['care_com_boolean'] ? 'true' : 'false',
                        'entity_type' => 'caregiver',
                    ],
                ]);
            }
        }
    }

    protected function timestampToDate(?int $timestamp): ?string
    {
        if (! $timestamp) {
            return null;
        }

        try {
            $date = Carbon::createFromTimestampMs($timestamp);

            return $date->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function timestampToYear(mixed $value): ?int
    {
        if (! $value) {
            if (is_numeric($value)) {
                try {
                    return (int) Carbon::createFromTimestampMs($value)->format('Y');
                } catch (\Throwable $e) {
                    return null;
                }
            }

            return null;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return (int) $value;
    }

    /**
     * Scrape data from Bubble by automating browser interactions.
     */
    protected function scrapeBubbleData(string $bubbleUrl, ?int $limit = null): array
    {
        $allHits = [];
        $hasMorePages = true;
        $pageNumber = 1;
        $cookies = [];

        // Check and install Chrome if needed
        $this->ensureChromeInstalled();

        // Check and download ChromeDriver if needed
        $chromeDriverPath = $this->ensureChromeDriverInstalled();

        $this->info('Launching headless Chrome browser...');

        // Create Panther client with ChromeDriver
        $userDataDir = sys_get_temp_dir().'/panther_chrome_'.uniqid();
        @mkdir($userDataDir, 0755, true);

        $client = Client::createChromeClient(
            $chromeDriverPath,
            [
                '--headless=new',
                '--no-sandbox',
                '--disable-dev-shm-usage',
                '--window-size=1920,1080',
                '--no-first-run',
                '--no-default-browser-check',
                '--disable-extensions',
                '--disable-default-apps',
                '--user-data-dir='.$userDataDir,
                '--disable-software-rasterizer',
                '--disable-gpu',
                '--enable-logging',
                '--v=1',
            ],
            [
                'port' => 9515,
                'capabilities' => [
                    'goog:loggingPrefs' => [
                        'browser' => 'ALL',
                    ],
                ],
            ]
        );

        try {
            while ($hasMorePages) {
                $this->info("Processing page {$pageNumber}...");

                // Load the Bubble page
                if ($pageNumber === 1) {
                    $this->line('Loading Bubble page and configuring data fields...');
                    $hits = $this->configureBubbleFieldsAndGetCookies($client, $bubbleUrl);
                } else {
                    // Reset intercepted response before clicking
                    $client->executeScript('window._capturedResponse = null; window._capturedTimestamp = null;');

                    // Click "Load 50 more items" button
                    $this->line('Clicking "Load 50 more items"...');
                    $loadMoreButton = $client->waitFor('.light-button.load-more', 30);
                    if ($loadMoreButton) {
                        $client->executeScript("
                            document.querySelector('.light-button.load-more').click();
                        ");
                        // Wait for the data to load and be intercepted
                        usleep(5000000); // 5 seconds

                        // Wait for intercepted response
                        $this->line('Waiting for Elasticsearch response...');
                        $hits = $this->waitForInterceptedResponse($client, 60);

                        if (! $hits || ! isset($hits['hits']['hits'])) {
                            $this->error('Failed to intercept response for page '.$pageNumber);
                            $hasMorePages = false;

                            continue;
                        }

                        $hits = $hits['hits']['hits'];
                    } else {
                        $this->info('No more "Load more" button available');
                        $hasMorePages = false;

                        continue;
                    }
                }

                if (! $hits) {
                    $this->error("Failed to fetch data for page {$pageNumber}");
                    break;
                }

                $allHits = array_merge($allHits, $hits);

                $this->line('  Retrieved '.count($hits).' records (total: '.count($allHits).')');

                // Check if we should continue
                if ($limit && count($allHits) >= $limit) {
                    $hasMorePages = false;
                    $this->info('Reached limit of '.$limit.' records');
                } elseif (empty($hits) || count($hits) < 50) {
                    // Less than 50 records means no more pages
                    $hasMorePages = false;
                    $this->info('No more pages available');
                } else {
                    $pageNumber++;
                }
            }

            if ($limit) {
                $allHits = array_slice($allHits, 0, $limit);
            }

            $this->info('Total records fetched: '.count($allHits));
        } finally {
            $client->quit();
            $this->info('Browser closed');
        }

        return $allHits;
    }

    /**
     * Configure Bubble fields and return intercepted hits.
     */
    protected function configureBubbleFieldsAndGetCookies(Client $client, string $bubbleUrl): array
    {
        // Navigate to the Bubble page
        $crawler = $client->request('GET', $bubbleUrl);

        // Wait for page to load
        $client->waitFor('body', 30);
        usleep(5000000); // 5 seconds wait for potential popup

        // Dismiss upgrade popup
        $client->executeScript("
            const popupTitle = document.querySelector('.popup-title');
            if (popupTitle && popupTitle.textContent.includes('Upgrade Bubble Version')) {
                const cancelBtn = document.querySelector('.btn.btn-cancel');
                if (cancelBtn) cancelBtn.click();
            }
        ");
        usleep(1000000);

        // Click tab-caption
        $client->executeScript("
            const tabCaption = document.querySelector('div.tab-caption:nth-child(3)');
            if (tabCaption) tabCaption.click();
        ");
        usleep(2000000);

        // Set up XHR interceptor
        $this->setupResponseInterceptor($client);
        usleep(1000000);

        // Click Caregivers nested view
        $client->executeScript("
            const nestedViewTitle = document.querySelector('.nested-view-title');
            if (nestedViewTitle && nestedViewTitle.textContent.includes('Caregivers')) {
                nestedViewTitle.click();
            } else {
                const elements = document.querySelectorAll('*');
                for (const el of elements) {
                    if (typeof el.className === 'string' && el.className.includes('nested-view-title') && el.textContent.includes('Caregivers')) {
                        el.click();
                        return;
                    }
                }
            }
        ");
        usleep(2000000);

        // Click Switch to live database
        $client->executeScript("
            const switchDb = document.querySelector('.switch-db');
            if (switchDb) switchDb.click();
        ");
        usleep(5000000);

        // Clear previous response
        $client->executeScript('window._capturedResponse = null;');

        // Click additional fields
        $client->executeScript("
            const buttons = document.querySelectorAll('.light-button');
            for (const btn of buttons) {
                if (btn.textContent.includes('additional fields')) {
                    btn.click();
                    return;
                }
            }
        ");
        usleep(2000000);

        // Click select all
        $client->executeScript("
            const selectAll = document.querySelector('.select-all.bubble-ui.light-grey-btn');
            if (selectAll) selectAll.click();
        ");
        usleep(1000000);

        // Click SAVE
        $client->executeScript("
            const saveButton = document.querySelector('.btn.btn-create.bubble-ui');
            if (saveButton) saveButton.click();
        ");

        // Wait for intercepted response
        $this->line('Waiting for Elasticsearch response...');
        $responseData = $this->waitForInterceptedResponse($client, 60);

        if ($responseData && isset($responseData['hits']['hits'])) {
            $count = count($responseData['hits']['hits']);
            $this->line("Successfully intercepted API response: {$count} records");

            return $responseData['hits']['hits'];
        }

        $this->error('Failed to intercept Elasticsearch response');

        return [];
    }

    /**
     * Set up XHR interceptor to capture Elasticsearch responses.
     */
    protected function setupResponseInterceptor(Client $client): void
    {
        $client->executeScript("
            if (!window._responseInterceptorSetup) {
                window._capturedResponse = null;
                window._capturedTimestamp = null;
                window._responseInterceptorSetup = true;
                
                const OriginalXHR = window.XMLHttpRequest;
                
                function wrapXHR(xhrInstance) {
                    const originalOpen = xhrInstance.open;
                    const originalSend = xhrInstance.send;
                    
                    xhrInstance.open = function(method, url) {
                        this._interceptedUrl = url;
                        return originalOpen.apply(this, arguments);
                    };
                    
                    xhrInstance.send = function(body) {
                        const xhr = this;
                        xhr.addEventListener('loadend', function() {
                            if (xhr._interceptedUrl && 
                                xhr._interceptedUrl.includes('elasticsearch/search')) {
                                try {
                                    const response = JSON.parse(xhr.responseText);
                                    window._capturedResponse = response;
                                    window._capturedTimestamp = Date.now();
                                } catch(e) {}
                            }
                        });
                        return originalSend.apply(this, arguments);
                    };
                    
                    return xhrInstance;
                }
                
                window.XMLHttpRequest = function() {
                    const xhr = new OriginalXHR();
                    return wrapXHR(xhr);
                };
                
                Object.keys(OriginalXHR).forEach(key => {
                    window.XMLHttpRequest[key] = OriginalXHR[key];
                });
                window.XMLHttpRequest.prototype = OriginalXHR.prototype;
                
                const originalFetch = window.fetch;
                window.fetch = function(...args) {
                    const url = args[0];
                    if (typeof url === 'string' && url.includes('elasticsearch/search')) {
                        return originalFetch.apply(this, args).then(async function(response) {
                            const clone = response.clone();
                            try {
                                const data = await clone.json();
                                window._capturedResponse = data;
                                window._capturedTimestamp = Date.now();
                            } catch(e) {}
                            return response;
                        });
                    }
                    return originalFetch.apply(this, args);
                };
            }
        ");
    }

    /**
     * Wait for intercepted response to be captured.
     */
    protected function waitForInterceptedResponse(Client $client, int $timeoutSeconds = 30): ?array
    {
        $startTime = time();

        while (time() - $startTime < $timeoutSeconds) {
            $response = $client->executeScript('return window._capturedResponse;');
            $timestamp = $client->executeScript('return window._capturedTimestamp;');

            if ($response) {
                return $response;
            }

            usleep(500000);
        }

        return null;
    }

    /**
     * Ensure Chrome is installed, install if needed.
     */
    protected function ensureChromeInstalled(): void
    {
        // Check if Chrome is available
        $chromePaths = [
            '/usr/bin/google-chrome',
            '/usr/bin/google-chrome-stable',
            '/usr/bin/chromium-browser',
            '/usr/bin/chromium',
            '/usr/local/bin/google-chrome',
            '/snap/bin/chromium',
        ];

        $chromeFound = null;
        foreach ($chromePaths as $path) {
            if (file_exists($path) && is_executable($path)) {
                $chromeFound = $path;
                break;
            }
        }

        // Also check via which command
        if (! $chromeFound) {
            $output = [];
            exec('which google-chrome google-chrome-stable chromium-browser chromium 2>/dev/null', $output, $returnCode);
            if ($returnCode === 0 && ! empty($output)) {
                $chromeFound = trim($output[0]);
            }
        }

        if ($chromeFound) {
            $version = shell_exec("{$chromeFound} --version 2>&1");
            $this->line('Chrome found: '.trim($version));

            return;
        }

        $this->warn('Chrome not found. Installing...');

        // Detect OS
        $os = PHP_OS_FAMILY;

        if ($os === 'Linux') {
            $this->installChromeLinux();
        } elseif ($os === 'Darwin') {
            $this->installChromeMac();
        } else {
            $this->error("Unsupported OS: {$os}. Please install Chrome manually.");
            exit(1);
        }
    }

    /**
     * Install Chrome on Linux.
     */
    protected function installChromeLinux(): void
    {
        $this->line('Installing Google Chrome on Linux...');

        // Try apt-get (Debian/Ubuntu)
        if (file_exists('/usr/bin/apt-get')) {
            $this->executeShellCommand('wget -q -O - https://dl.google.com/linux/linux_signing_key.pub | sudo apt-key add -');
            $this->executeShellCommand("sudo sh -c 'echo \"deb [arch=amd64] http://dl.google.com/linux/chrome/deb/ stable main\" >> /etc/apt/sources.list.d/google-chrome.list'");
            $this->executeShellCommand('sudo apt-get update -qq');
            $this->executeShellCommand('sudo apt-get install -y google-chrome-stable');
        } elseif (file_exists('/usr/bin/dnf')) {
            // Fedora/RHEL
            $this->executeShellCommand('sudo dnf install -y fedora-workstation-repositories');
            $this->executeShellCommand('sudo dnf config-manager --set-enabled google-chrome');
            $this->executeShellCommand('sudo dnf install -y google-chrome-stable');
        } elseif (file_exists('/usr/bin/pacman')) {
            // Arch Linux
            $this->executeShellCommand('sudo pacman -S --noconfirm google-chrome');
        } else {
            $this->error('Unsupported Linux distribution. Please install Chrome manually.');
            exit(1);
        }

        // Verify installation
        $output = [];
        exec('google-chrome-stable --version 2>&1', $output);
        if (! empty($output)) {
            $this->info('Chrome installed successfully: '.trim($output[0]));
        } else {
            $this->error('Chrome installation failed. Please install manually.');
            exit(1);
        }
    }

    /**
     * Install Chrome on macOS.
     */
    protected function installChromeMac(): void
    {
        $this->line('Installing Google Chrome on macOS...');

        // Check if Homebrew is installed
        $output = [];
        exec('which brew 2>/dev/null', $output, $returnCode);
        if ($returnCode !== 0) {
            $this->error('Homebrew not found. Please install Homebrew first:');
            $this->line('/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"');
            exit(1);
        }

        $this->executeShellCommand('brew install --cask google-chrome');

        // Verify installation
        $output = [];
        exec('/Applications/Google\ Chrome.app/Contents/MacOS/Google\ Chrome --version 2>&1', $output);
        if (! empty($output)) {
            $this->info('Chrome installed successfully: '.trim($output[0]));
        } else {
            $this->error('Chrome installation failed. Please install manually.');
            exit(1);
        }
    }

    /**
     * Run a shell command and output progress.
     */
    protected function executeShellCommand(string $command): void
    {
        $this->line('  Running: '.$command);
        $output = [];
        $returnCode = 0;
        exec($command.' 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            $this->warn('  Command returned code: '.$returnCode);
            if (! empty($output)) {
                $lastLines = array_slice($output, -3);
                foreach ($lastLines as $line) {
                    $this->line('    '.$line);
                }
            }
        }
    }

    /**
     * Ensure ChromeDriver is downloaded and available.
     */
    protected function ensureChromeDriverInstalled(): string
    {
        $driversDir = storage_path('app/drivers');
        $chromeDriverPath = $driversDir.'/chromedriver';

        // Check if ChromeDriver already exists
        if (file_exists($chromeDriverPath) && is_executable($chromeDriverPath)) {
            $version = shell_exec("{$chromeDriverPath} --version 2>&1");
            $this->line('ChromeDriver found: '.trim($version));

            return $chromeDriverPath;
        }

        $this->warn('ChromeDriver not found. Downloading...');

        // Create drivers directory
        if (! is_dir($driversDir)) {
            @mkdir($driversDir, 0755, true);
            $this->line('Created drivers directory');
        }

        // Get Chrome version
        $chromeVersion = $this->getChromeVersion();
        if (! $chromeVersion) {
            $this->error('Cannot determine Chrome version');

            return $this->downloadLatestChromeDriver($driversDir);
        }

        $majorVersion = explode('.', $chromeVersion)[0];
        $this->line("Detected Chrome major version: {$majorVersion}");

        // Try to download matching ChromeDriver version
        if ($this->downloadChromeDriverForVersion($driversDir, $majorVersion)) {
            return $chromeDriverPath;
        }

        // Fallback to latest version
        return $this->downloadLatestChromeDriver($driversDir);
    }

    /**
     * Get installed Chrome version.
     */
    protected function getChromeVersion(): ?string
    {
        $chromePaths = [
            'google-chrome-stable',
            'google-chrome',
            'chromium-browser',
            'chromium',
        ];

        foreach ($chromePaths as $cmd) {
            $output = [];
            exec("{$cmd} --version 2>&1", $output);
            if (! empty($output)) {
                // Extract version number from output like "Google Chrome 147.0.7727.55"
                if (preg_match('/(\d+\.\d+\.\d+\.\d+)/', $output[0], $matches)) {
                    return $matches[1];
                }
            }
        }

        return null;
    }

    /**
     * Download ChromeDriver for specific version.
     */
    protected function downloadChromeDriverForVersion(string $driversDir, string $majorVersion): bool
    {
        $this->line("Attempting to download ChromeDriver for version {$majorVersion}...");

        // Try new ChromeDriver JSON endpoint (Chrome 115+)
        $versions = $this->getChromeDriverVersions();
        if ($versions) {
            $url = $this->findChromeDriverUrl($versions, $majorVersion);
            if ($url) {
                return $this->downloadChromeDriverFromUrl($driversDir, $url);
            }
        }

        return false;
    }

    /**
     * Get available ChromeDriver versions from JSON endpoint.
     */
    protected function getChromeDriverVersions(): ?array
    {
        try {
            $response = Http::timeout(10)->get('https://googlechromelabs.github.io/chrome-for-testing/known-good-versions-with-downloads.json');
            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Throwable $e) {
            // Fallback will handle this
        }

        return null;
    }

    /**
     * Find ChromeDriver download URL for specific version.
     */
    protected function findChromeDriverUrl(array $versions, string $majorVersion): ?string
    {
        $allVersions = $versions['versions'] ?? [];

        // Filter for matching major version and get the latest one
        $matchingVersions = array_filter($allVersions, function ($v) use ($majorVersion) {
            return explode('.', $v['version'])[0] === $majorVersion;
        });

        if (empty($matchingVersions)) {
            return null;
        }

        // Get the latest version matching our major version
        $latestVersion = end($matchingVersions);
        $downloads = $latestVersion['downloads']['chromedriver'] ?? [];

        // Find linux64 build
        foreach ($downloads as $download) {
            if (isset($download['platform']) && strpos($download['platform'], 'linux') !== false) {
                return $download['url'];
            }
        }

        return null;
    }

    /**
     * Download ChromeDriver from URL.
     */
    protected function downloadChromeDriverFromUrl(string $driversDir, string $url): bool
    {
        $this->line("Downloading ChromeDriver from: {$url}");

        $zipPath = $driversDir.'/chromedriver.zip';

        try {
            $response = Http::timeout(60)->get($url);
            if (! $response->successful()) {
                $this->error("Failed to download ChromeDriver: HTTP {$response->status()}");

                return false;
            }

            file_put_contents($zipPath, $response->body());

            // Extract zip
            $zip = new \ZipArchive;
            if ($zip->open($zipPath) === true) {
                $zip->extractTo($driversDir);
                $zip->close();

                // Find the extracted chromedriver binary
                $extractedPath = $this->findExtractedChromeDriver($driversDir);
                if ($extractedPath) {
                    chmod($extractedPath, 0755);
                    $version = shell_exec("{$extractedPath} --version 2>&1");
                    $this->info('ChromeDriver installed: '.trim($version));

                    // Clean up zip
                    @unlink($zipPath);

                    // Create symlink to standard path if needed
                    if ($extractedPath !== $driversDir.'/chromedriver') {
                        @symlink($extractedPath, $driversDir.'/chromedriver');
                    }

                    return true;
                }
            }

            $this->error('Failed to extract ChromeDriver zip');

            return false;
        } catch (\Throwable $e) {
            $this->error('ChromeDriver download failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Find extracted chromedriver binary.
     */
    protected function findExtractedChromeDriver(string $driversDir): ?string
    {
        // Check for chromedriver directly
        if (file_exists($driversDir.'/chromedriver')) {
            return $driversDir.'/chromedriver';
        }

        // Check in subdirectories (ChromeDriver 115+ extracts to chromedriver-linux64/)
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($driversDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getFilename() === 'chromedriver' && $file->isFile()) {
                return $file->getPathname();
            }
        }

        return null;
    }

    /**
     * Download latest ChromeDriver as fallback.
     */
    protected function downloadLatestChromeDriver(string $driversDir): string
    {
        $this->warn('Falling back to BDI driver detection...');

        // Try using BDI (Browser Driver Installer)
        $bdiPath = base_path('vendor/bin/bdi');
        if (file_exists($bdiPath)) {
            $this->executeShellCommand("{$bdiPath} detect {$driversDir} 2>&1");
            if (file_exists($driversDir.'/chromedriver')) {
                chmod($driversDir.'/chromedriver', 0755);
                $version = shell_exec("{$driversDir}/chromedriver --version 2>&1");
                $this->info('ChromeDriver installed via BDI: '.trim($version));

                return $driversDir.'/chromedriver';
            }
        }

        $this->error('Failed to install ChromeDriver automatically.');
        $this->line('Please run: ./vendor/bin/bdi detect '.$driversDir);
        exit(1);
    }
}
