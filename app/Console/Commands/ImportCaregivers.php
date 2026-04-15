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
        $apiEndpoint = null;

        $this->info('Launching headless Chrome browser...');

        // Create Panther client with ChromeDriver
        $chromeDriverPath = base_path('drivers/chromedriver');
        $userDataDir = sys_get_temp_dir() . '/panther_chrome_' . uniqid();
        @mkdir($userDataDir, 0755, true);
        
        $client = Client::createChromeClient(
            $chromeDriverPath,
            [
                '--no-sandbox',
                '--disable-dev-shm-usage',
                '--window-size=1920,1080',
                '--no-first-run',
                '--no-default-browser-check',
                '--disable-extensions',
                '--disable-default-apps',
                '--user-data-dir=' . $userDataDir,
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

                $this->line("  Retrieved ".count($hits).' records (total: '.count($allHits).')');

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

            $this->info("Total records fetched: ".count($allHits));
        } finally {
            $client->quit();
            $this->info('Browser closed');
        }

        return $allHits;
    }

    /**
     * Configure Bubble fields and return browser cookies.
     */
    protected function configureBubbleFieldsAndGetCookies(Client $client, string $bubbleUrl): array
    {
        // Navigate to the Bubble page
        $crawler = $client->request('GET', $bubbleUrl);

        // Wait for page to load
        $client->waitFor('body', 30);
        
        // Inject script to log when page is ready
        $client->executeScript("
            const startTime = Date.now();
            const checkReady = () => {
                const elapsed = ((Date.now() - startTime) / 1000).toFixed(3);
                if (document.readyState === 'complete') {
                    console.log('page is loaded after ' + elapsed + ' seconds');
                } else {
                    setTimeout(checkReady, 100);
                }
            };
            checkReady();
        ");
        
        usleep(5000000); // 5 seconds wait for potential popup

        // Wait for page-ready console log
        $this->line('Waiting for page to signal readiness...');
        $this->waitForConsoleLog($client, 'page is loaded after', 60, true);

        // Check for and dismiss "Upgrade Bubble Version" popup
        $this->line('Checking for upgrade popup...');
        $client->executeScript("
            const popupTitle = document.querySelector('.popup-title');
            if (popupTitle && popupTitle.textContent.includes('Upgrade Bubble Version')) {
                const cancelBtn = document.querySelector('.btn.btn-cancel');
                if (cancelBtn) {
                    cancelBtn.click();
                    console.log('Dismissed Upgrade Bubble Version popup');
                }
            }
        ");
        usleep(1000000); // 1 second

        // Click "tab-caption app data" element
        $this->line('Clicking "tab-caption app data"...');
        $client->executeScript("
            const tabCaption = document.querySelector('div.tab-caption:nth-child(3)');
            if (tabCaption) {
                tabCaption.click();
                console.log('Clicked tab-caption app data');
            } else {
                console.log('tab-caption app data not found');
            }
        ");
        usleep(2000000); // 2 seconds

        // Set up XHR interceptor before any further interactions
        $this->line('Setting up XHR response interceptor...');
        $this->setupResponseInterceptor($client);
        usleep(1000000);

        // Click "Caregivers" nested view title
        $this->line('Clicking "Caregivers" nested view...');
        $client->executeScript("
            const nestedViewTitle = document.querySelector('.nested-view-title');
            if (nestedViewTitle && nestedViewTitle.textContent.includes('Caregivers')) {
                nestedViewTitle.click();
                console.log('Clicked Caregivers nested view');
            } else {
                // Try finding any element with Caregivers text
                const elements = document.querySelectorAll('*');
                for (const el of elements) {
                    if (typeof el.className === 'string' && el.className.includes('nested-view-title') && el.textContent.includes('Caregivers')) {
                        el.click();
                        console.log('Clicked Caregivers nested view');
                        return;
                    }
                }
                console.log('Caregivers nested view not found');
            }
        ");
        usleep(2000000); // 2 seconds

        // Click "Switch to live database" button
        $this->line('Clicking "Switch to live database"...');
        $client->executeScript("
            const switchDb = document.querySelector('.switch-db');
            if (switchDb) {
                switchDb.click();
                console.log('Clicked Switch to live database');
            } else {
                console.log('Switch to live database button not found');
            }
        ");

        // Wait for page to reload and be ready
        $client->waitFor('body', 30);
        usleep(5000000); // 5 seconds

        // Click "XXX additional fields" button - find dynamically
        $this->line('Finding and clicking "additional fields" button...');
        $client->executeScript("
            const buttons = document.querySelectorAll('.light-button');
            for (const btn of buttons) {
                if (btn.textContent.includes('additional fields')) {
                    btn.click();
                    console.log('Clicked: ' + btn.textContent.trim());
                    return;
                }
            }
            console.log('Additional fields button not found');
        ");

        // Wait for modal to appear
        usleep(2000000);

        // Click "(select all)" button in the modal
        $this->line('Clicking "(select all)" button...');
        $client->executeScript("
            const selectAll = document.querySelector('.select-all.bubble-ui.light-grey-btn');
            if (selectAll) {
                selectAll.click();
                console.log('Clicked select all');
            } else {
                console.log('Select all button not found');
            }
        ");

        usleep(1000000);

        // Clear any previous intercepted response
        $client->executeScript("window._capturedResponse = null;");

        // Click "SAVE" button
        $this->line('Clicking "SAVE" button...');
        $client->executeScript("
            const saveButton = document.querySelector('.btn.btn-create.bubble-ui');
            if (saveButton) {
                saveButton.click();
                console.log('Clicked SAVE');
            } else {
                console.log('Save button not found');
            }
        ");

        // Wait for and get the intercepted Elasticsearch response
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
     * Extract data either via API with cookies or from page context.
     */
    protected function extractDataViaApiOrPage(Client $client, string $bubbleUrl, array $cookies, int $offset): ?array
    {
        // Try to make API request with cookies
        try {
            $this->line('Attempting API request with browser cookies...');
            
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Referer' => $bubbleUrl,
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            ])
            ->withCookies($cookies, 'bubble.io')
            ->timeout(30)
            ->post(self::BUBBLE_API_URL, [
                'index' => 'user',
                'query' => ['bool' => ['must' => []]],
                'from' => $offset,
                'size' => 50,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['hits']['hits'])) {
                    return $data['hits']['hits'];
                }
            }
            
            $this->line('API request failed: '.$response->status());
        } catch (\Throwable $e) {
            $this->line('API request exception: '.$e->getMessage());
        }

        // Fallback: Try to extract from page JavaScript context
        $this->line('Falling back to page context extraction...');
        return $this->extractDataFromPageContext($client, $bubbleUrl, $cookies, $offset);
    }

    /**
     * Extract data from page JavaScript context.
     */
    protected function extractDataFromPageContext(Client $client, string $bubbleUrl, array $cookies, int $offset): ?array
    {
        // Try to access Bubble's internal data structures
        $data = $client->executeScript("
            // Try common Bubble data locations
            if (window.bubble && window.bubble.data) {
                return window.bubble.data;
            }
            
            // Try to find data in React/Vue internals
            if (window.__REACT_DEVTOOLS_GLOBAL_HOOK__) {
                // React app - try to find data store
            }
            
            // Try to access any global data store
            const keys = Object.keys(window).filter(k => 
                k.includes('bubble') || k.includes('data') || k.includes('user')
            );
            
            for (const key of keys) {
                const value = window[key];
                if (value && typeof value === 'object' && value.hits) {
                    return value;
                }
            }
            
            // Try to get from performance API (recent XHR responses)
            if (window.performance && window.performance.getEntriesByType) {
                const resources = window.performance.getEntriesByType('resource');
                const elasticCalls = resources.filter(r => 
                    r.name && r.name.includes('elasticsearch/search')
                );
                if (elasticCalls.length > 0) {
                    console.log('Found elasticsearch calls in performance API:', elasticCalls.length);
                }
            }
            
            return null;
        ");

        if ($data && isset($data['hits']['hits'])) {
            return $data['hits']['hits'];
        }

        // Last resort: Try to use browser cookies to make a better API request
        $this->line('Attempting enhanced API request with session data...');
        return $this->makeEnhancedApiRequest($bubbleUrl, $cookies, $offset);
    }

    /**
     * Make an enhanced API request trying to mimic Bubble's actual format.
     */
    protected function makeEnhancedApiRequest(string $bubbleUrl, array $cookies, int $offset): ?array
    {
        try {
            // Try different request formats that Bubble might expect
            $testPayloads = [
                // Format 1: Simple search
                [
                    'index' => 'user',
                    'query' => ['bool' => ['must' => []]],
                    'from' => $offset,
                    'size' => 50,
                ],
                // Format 2: With type
                [
                    'type' => 'user',
                    'index' => 'user',
                    'query' => ['bool' => ['must' => []]],
                    'from' => $offset,
                    'size' => 50,
                ],
                // Format 3: Match all
                [
                    'index' => 'user',
                    'query' => ['match_all' => new \stdClass()],
                    'from' => $offset,
                    'size' => 50,
                ],
            ];

            foreach ($testPayloads as $i => $payload) {
                $this->line("  Trying API format ".($i + 1)."...");
                
                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Referer' => $bubbleUrl,
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Origin' => 'https://bubble.io',
                ])
                ->withCookies($cookies, 'bubble.io')
                ->timeout(30)
                ->post(self::BUBBLE_API_URL, $payload);

                if ($response->successful()) {
                    $data = $response->json();
                    if (isset($data['hits']['hits'])) {
                        $this->line("  Success with format ".($i + 1));
                        return $data['hits']['hits'];
                    }
                }
            }
            
            $this->line('All API formats failed');
        } catch (\Throwable $e) {
            $this->line('Enhanced API request exception: '.$e->getMessage());
        }

        return null;
    }

    /**
     * Wait for a specific console log message to appear.
     */
    protected function waitForConsoleLog(Client $client, string $expectedMessage, int $timeoutSeconds = 30, bool $debug = false): void
    {
        $startTime = time();
        $lastLogCount = 0;
        
        while (time() - $startTime < $timeoutSeconds) {
            try {
                $logs = $client->manage()->getLog('browser');
                
                if ($debug && count($logs) > $lastLogCount) {
                    // Show new logs
                    for ($i = $lastLogCount; $i < count($logs); $i++) {
                        $log = $logs[$i];
                        $message = $log['message'] ?? '';
                        $this->line("  Browser log [{$log['level']}]: ".$message);
                    }
                    $lastLogCount = count($logs);
                }
                
                foreach ($logs as $log) {
                    $message = $log['message'] ?? '';
                    // Log might be in different formats, check multiple possibilities
                    if (is_array($message)) {
                        $message = json_encode($message);
                    }
                    
                    if (stripos((string)$message, $expectedMessage) !== false) {
                        // If message contains "0.000", skip it - we want actual load time
                        if (preg_match('/page is loaded after ([0-9.]+)/', (string)$message, $matches)) {
                            $seconds = (float) $matches[1];
                            if ($seconds > 0) {
                                $this->line("Found expected log: ".$expectedMessage." (".$seconds."s)");
                                return;
                            }
                        } else {
                            // No seconds pattern found, accept it
                            $this->line("Found expected log: ".$expectedMessage);
                            return;
                        }
                    }
                }
            } catch (\Throwable $e) {
                if ($debug) {
                    $this->line("  Log error: ".$e->getMessage());
                }
            }
            
            usleep(500000); // 0.5 seconds
        }
        
        $this->line("Timeout waiting for console log: ".$expectedMessage);
    }

    /**
     * Set up XMLHttpRequest interceptor to capture Elasticsearch responses.
     */
    protected function setupResponseInterceptor(Client $client): void
    {
        $client->executeScript("
            if (!window._responseInterceptorSetup) {
                window._capturedResponse = null;
                window._responseInterceptorSetup = true;
                
                console.log('Setting up XHR response interceptor...');
                
                const OriginalXHR = window.XMLHttpRequest;
                
                function wrapXHR(xhrInstance) {
                    const originalOpen = xhrInstance.open;
                    const originalSend = xhrInstance.send;
                    
                    xhrInstance.open = function(method, url) {
                        this._interceptedUrl = url;
                        this._interceptedMethod = method;
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
                                    console.log('Intercepted Elasticsearch response with', 
                                        response.hits?.hits?.length || 0, 'hits');
                                } catch(e) {
                                    console.error('Failed to parse Elasticsearch response:', e);
                                }
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
                
                // Copy static properties
                Object.keys(OriginalXHR).forEach(key => {
                    window.XMLHttpRequest[key] = OriginalXHR[key];
                });
                window.XMLHttpRequest.prototype = OriginalXHR.prototype;
                
                // Also intercept fetch API
                const originalFetch = window.fetch;
                window.fetch = function(...args) {
                    const url = args[0];
                    if (typeof url === 'string' && url.includes('elasticsearch/search')) {
                        console.log('Intercepting fetch to elasticsearch/search');
                        return originalFetch.apply(this, args).then(async function(response) {
                            const clone = response.clone();
                            try {
                                const data = await clone.json();
                                window._capturedResponse = data;
                                window._capturedTimestamp = Date.now();
                                console.log('Intercepted fetch Elasticsearch response with',
                                    data.hits?.hits?.length || 0, 'hits');
                            } catch(e) {
                                console.error('Failed to parse fetch response:', e);
                            }
                            return response;
                        });
                    }
                    return originalFetch.apply(this, args);
                };
                
                console.log('XHR response interceptor setup complete');
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
            
            usleep(500000); // 0.5 seconds
        }
        
        return null;
    }
}
