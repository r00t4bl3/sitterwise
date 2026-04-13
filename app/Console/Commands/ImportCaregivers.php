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

#[Signature('caregivers:import {file : Path to JSON file} {--force : Overwrite existing records} {--dry-run : Preview without saving} {--limit= : Limit number of records} {--no-transaction : Disable transaction}')]
#[Description('Import caregivers from JSON file')]
class ImportCaregivers extends Command
{
    protected $successCount = 0;

    protected $errorCount = 0;

    protected $errors = [];

    public function handle(): int
    {
        $file = $this->argument('file');
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');
        $limit = $this->option('limit');
        $useTransaction = ! $this->option('no-transaction');

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
}
