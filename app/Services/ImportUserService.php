<?php

namespace App\Services;

use App\Enums\AssignmentResolution;
use App\Enums\BookingStatus;
use App\Enums\CaregiverStatus;
use App\Enums\ClientType;
use App\Enums\LocationType;
use App\Enums\ServiceType;
use App\Enums\SpecialConsideration;
use App\Models\Booking;
use App\Models\BookingGroup;
use App\Models\Caregiver;
use App\Models\CaregiverPayout;
use App\Models\Client as ClientModel;
use App\Models\ClientPayment;
use App\Models\Hotel;
use App\Models\Traits\Phone as PhoneTrait;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ImportUserService
{
    // ------------------------------------------------------------------
    // Pass 1 — Users
    // ------------------------------------------------------------------

    /**
     * Extract user data from staged records and bulk upsert into users table.
     * Returns the mapping of bubble_id → User for downstream passes.
     *
     * @param  array<int, array{source: array<string, mixed>, id: string}>  $hits
     * @return array<string, User>
     */
    public function passUsers(array $hits, bool $force, ?\Closure $onProgress = null): array
    {
        file_put_contents('php://stdout', "  Loading existing users...\n");
        $existingByBubble = User::whereNotNull('bubble_id')->get()->keyBy('bubble_id')->all();
        $existingByEmail = User::all()->keyBy('email')->all();
        file_put_contents('php://stdout', '  Loaded '.count($existingByBubble).' by bubble_id, '.count($existingByEmail)." by email\n");

        $newRecords = [];
        $newBubbleIds = [];
        $updateIds = [];
        $updateData = [];
        $defaultPasswordHash = Hash::make('changeme123');

        $i = 0;
        foreach ($hits as $hit) {
            $i++;
            if ($i % 500 === 0) {
                file_put_contents('php://stdout', "  Processing record $i/".count($hits)."\n");
            }

            $source = $hit['source'];
            $externalId = $hit['id'];

            $email = $source['authentication']['email']['email'] ?? null;
            if (! $email) {
                if ($onProgress) {
                    $onProgress();
                }

                continue;
            }

            $names = self::parseSourceNames($source, $email);
            $fullName = trim($names['first'].' '.$names['last']);
            $role = $source['role_permissions_option_role'] ?? 'caregiver';
            $photoUrl = self::parsePhotoUrl(
                $source['profile_photo_url_text'] ?? $source['profile_photo_file'] ?? null
            );

            $existing = $existingByBubble[$externalId] ?? null;

            if ($existing) {
                $updateIds[] = $existing->id;
                $updateData[] = [
                    'name' => $fullName,
                    'role' => $role,
                    'profile_photo_url' => $photoUrl,
                ];
                $existing->name = $fullName;
                $existing->role = $role;
            } else {
                $emailUser = $existingByEmail[$email] ?? null;

                if ($emailUser) {
                    if ($emailUser->bubble_id && $emailUser->bubble_id !== $externalId) {
                        continue;
                    }
                    $emailUser->update(['bubble_id' => $externalId, 'name' => $fullName, 'role' => $role, 'profile_photo_url' => $photoUrl]);
                    $existingByBubble[$externalId] = $emailUser;
                } else {
                    $newRecords[] = [
                        'bubble_id' => $externalId,
                        'email' => $email,
                        'name' => $fullName,
                        'role' => $role,
                        'profile_photo_url' => $photoUrl,
                        'password' => isset($source['temporary_password_text']) && $source['temporary_password_text']
                            ? Hash::make($source['temporary_password_text'])
                            : $defaultPasswordHash,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    $newBubbleIds[] = $externalId;
                }
            }
            if ($onProgress) {
                $onProgress();
            }
        }

        file_put_contents('php://stdout', '  Loop done. New: '.count($newRecords).', Updates: '.count($updateIds)."\n");

        if (! empty($newRecords)) {
            foreach (array_chunk($newRecords, 100) as $chunk) {
                file_put_contents('php://stdout', 'Inserting chunk of '.count($chunk)." users\n");
                User::query()->insert($chunk);
            }

            $newlyCreated = User::whereIn('bubble_id', $newBubbleIds)->get()->keyBy('bubble_id');
            $existingByBubble = array_merge($existingByBubble, $newlyCreated->all());
        }

        if (! empty($updateIds)) {
            foreach (array_chunk($updateIds, 100) as $i => $chunk) {
                file_put_contents('php://stdout', 'Updating chunk of '.count($chunk)." users\n");
                $dataChunk = array_slice($updateData, $i * 100, 100);
                foreach ($chunk as $j => $id) {
                    User::where('id', $id)->update($dataChunk[$j]);
                }
            }
        }

        return $existingByBubble;
    }

    /**
     * Process a single user hit (per-record approach for ImportBubbleDatabase compatibility).
     *
     * @param  array<string, User>  $usersByBubbleId
     */
    public function processUserHit(array $source, string $externalId, bool $force, array $usersByBubbleId = []): void
    {
        $email = $source['authentication']['email']['email'] ?? null;
        if (! $email) {
            throw new \Exception("User $externalId is missing an email address.");
        }

        $names = self::parseSourceNames($source, $email);
        $fullName = trim($names['first'].' '.$names['last']);
        $role = $source['role_permissions_option_role'] ?? 'caregiver';
        $photoUrl = self::parsePhotoUrl($source['profile_photo_url_text'] ?? $source['profile_photo_file'] ?? null);

        $user = $usersByBubbleId[$externalId] ?? User::where('bubble_id', $externalId)->first();

        if (! $user) {
            $user = User::where('email', $email)->first();
            if ($user) {
                if ($user->bubble_id && $user->bubble_id !== $externalId) {
                    throw new \Exception("Email collision: $email is already linked to Bubble ID {$user->bubble_id}. Record $externalId skipped.");
                }
                $user->update(['bubble_id' => $externalId]);
            } else {
                $user = User::create([
                    'bubble_id' => $externalId,
                    'email' => $email,
                    'name' => $fullName,
                    'password' => Hash::make($source['temporary_password_text'] ?? 'changeme123'),
                ]);
            }
        }

        $user->update(['name' => $fullName, 'role' => $role, 'profile_photo_url' => $photoUrl]);

        if ($role === 'caregiver' || $role === 'caregiver_applicant') {
            $data = self::extractCaregiverData($source, $user);
            Caregiver::updateOrCreate(['user_id' => $user->id], $data);
        } elseif ($role === 'client') {
            $data = self::extractClientData($source, $user);
            ClientModel::updateOrCreate(['user_id' => $user->id], $data);
        }
    }

    // ------------------------------------------------------------------
    // Pass 2 — Caregivers
    // ------------------------------------------------------------------

    /**
     * @param  array<int, array{source: array<string, mixed>, id: string}>  $hits
     * @return array<int, int> caregiver IDs that were created/updated
     */
    public function passCaregivers(array $hits, array $usersByBubbleId, bool $force, ?\Closure $onProgress = null): array
    {
        $caregiversByUserId = Caregiver::query()->get()->keyBy('user_id')->all();
        $now = now();

        $newRecords = [];
        $newUserIds = [];
        $updateIds = [];
        $updateData = [];

        foreach ($hits as $hit) {
            if ($onProgress) {
                $onProgress();
            }
            $source = $hit['source'];
            $role = $source['role_permissions_option_role'] ?? 'caregiver';
            if ($role !== 'caregiver' && $role !== 'caregiver_applicant') {
                continue;
            }

            $externalId = $hit['id'];
            $user = $usersByBubbleId[$externalId] ?? null;
            if (! $user) {
                continue;
            }

            $data = self::extractCaregiverData($source, $user);

            $existing = $caregiversByUserId[$user->id] ?? null;
            if ($existing) {
                $updateIds[] = $existing->id;
                $updateData[] = $data;
            } else {
                $newRecords[] = array_merge($data, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $newUserIds[] = $user->id;
            }
        }

        $createdIds = [];

        if (! empty($newRecords)) {
            foreach (array_chunk($newRecords, 100) as $chunk) {
                file_put_contents('php://stdout', 'Inserting chunk of '.count($chunk)." caregivers\n");
                Caregiver::query()->insert($chunk);
            }
            $createdIds = Caregiver::whereIn('user_id', $newUserIds)->pluck('id')->all();
        }

        $updatedIds = [];
        if (! empty($updateIds)) {
            file_put_contents('php://stdout', 'Updating '.count($updateIds)." caregivers individually\n");
            foreach ($updateIds as $i => $id) {
                Caregiver::where('id', $id)->update($updateData[$i]);
            }
            $updatedIds = $updateIds;
        }

        return array_merge($createdIds, $updatedIds);
    }

    // ------------------------------------------------------------------
    // Pass 3 — Clients
    // ------------------------------------------------------------------

    /**
     * @param  array<int, array{source: array<string, mixed>, id: string}>  $hits
     * @return array<int, int> client IDs that were created/updated
     */
    public function passClients(array $hits, array $usersByBubbleId, bool $force, ?\Closure $onProgress = null): array
    {
        $clientsByUserId = ClientModel::query()->get()->keyBy('user_id')->all();
        $now = now();

        $newRecords = [];
        $newUserIds = [];
        $updateIds = [];
        $updateData = [];

        foreach ($hits as $hit) {
            if ($onProgress) {
                $onProgress();
            }
            $source = $hit['source'];
            $role = $source['role_permissions_option_role'] ?? 'caregiver';
            if ($role !== 'client') {
                continue;
            }

            $externalId = $hit['id'];
            $user = $usersByBubbleId[$externalId] ?? null;
            if (! $user) {
                continue;
            }

            $data = self::extractClientData($source, $user);

            $existing = $clientsByUserId[$user->id] ?? null;
            if ($existing) {
                $updateIds[] = $existing->id;
                $updateData[] = $data;
            } else {
                $newRecords[] = array_merge($data, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $newUserIds[] = $user->id;
            }
        }

        $createdIds = [];

        if (! empty($newRecords)) {
            foreach (array_chunk($newRecords, 100) as $chunk) {
                file_put_contents('php://stdout', 'Inserting chunk of '.count($chunk)." clients\n");
                ClientModel::query()->insert($chunk);
            }
            $createdIds = ClientModel::whereIn('user_id', $newUserIds)->pluck('id')->all();
        }

        $updatedIds = [];
        if (! empty($updateIds)) {
            file_put_contents('php://stdout', 'Updating '.count($updateIds)." clients individually\n");
            foreach ($updateIds as $i => $id) {
                ClientModel::where('id', $id)->update($updateData[$i]);
            }
            $updatedIds = $updateIds;
        }

        return array_merge($createdIds, $updatedIds);
    }

    // ------------------------------------------------------------------
    // Pass 4 — Caregiver Educations
    // ------------------------------------------------------------------

    public function passCaregiverEducations(array $hits, array $usersByBubbleId, ?\Closure $onProgress = null): void
    {
        $batch = [];
        foreach ($hits as $hit) {
            if ($onProgress) {
                $onProgress();
            }
            $source = $hit['source'];
            $role = $source['role_permissions_option_role'] ?? 'caregiver';
            if ($role !== 'caregiver' && $role !== 'caregiver_applicant') {
                continue;
            }
            $user = $usersByBubbleId[$hit['id']] ?? null;
            if (! $user) {
                continue;
            }
            $caregiver = Caregiver::where('user_id', $user->id)->first();
            if (! $caregiver) {
                continue;
            }

            $now = now();
            if (! empty($source['high_school_name_text'])) {
                $batch[] = [
                    'caregiver_id' => $caregiver->id,
                    'education_type' => 'high_school',
                    'school_name' => $source['high_school_name_text'],
                    'graduation_year' => self::timestampToYear($source['graduation_year_date'] ?? null),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            if (! empty($source['college_name_text'])) {
                $batch[] = [
                    'caregiver_id' => $caregiver->id,
                    'education_type' => 'college',
                    'school_name' => $source['college_name_text'],
                    'graduation_year' => self::timestampToYear($source['college_graduation_year_text'] ?? null),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (empty($batch)) {
            return;
        }

        $cgIds = array_unique(array_column($batch, 'caregiver_id'));
        DB::table('caregiver_educations')->whereIn('caregiver_id', $cgIds)->delete();
        foreach (array_chunk($batch, 100) as $chunk) {
            file_put_contents('php://stdout', 'Inserting chunk of '.count($chunk)." caregiver_educations\n");
            DB::table('caregiver_educations')->insert($chunk);
        }
    }

    // ------------------------------------------------------------------
    // Pass 5 — Caregiver Experiences
    // ------------------------------------------------------------------

    public function passCaregiverExperiences(array $hits, array $usersByBubbleId, ?\Closure $onProgress = null): void
    {
        $batch = [];
        foreach ($hits as $hit) {
            if ($onProgress) {
                $onProgress();
            }
            $source = $hit['source'];
            $role = $source['role_permissions_option_role'] ?? 'caregiver';
            if ($role !== 'caregiver' && $role !== 'caregiver_applicant') {
                continue;
            }
            $user = $usersByBubbleId[$hit['id']] ?? null;
            if (! $user) {
                continue;
            }
            $caregiver = Caregiver::where('user_id', $user->id)->first();
            if (! $caregiver) {
                continue;
            }

            $now = now();
            for ($i = 1; $i <= 3; $i++) {
                $startKey = "childcare_experience_{$i}_start_date_date";
                $endKey = "childcare_experience_{$i}_end_date_date";
                $detailsKey = "childcare_experience_{$i}_details_text";
                if (! empty($source[$startKey]) || ! empty($source[$detailsKey])) {
                    $batch[] = [
                        'caregiver_id' => $caregiver->id,
                        'sequence' => $i,
                        'start_date' => self::timestampToDate($source[$startKey] ?? null),
                        'end_date' => self::timestampToDate($source[$endKey] ?? null),
                        'details' => $source[$detailsKey] ?? null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        if (empty($batch)) {
            return;
        }

        $cgIds = array_unique(array_column($batch, 'caregiver_id'));
        DB::table('caregiver_experiences')->whereIn('caregiver_id', $cgIds)->delete();
        foreach (array_chunk($batch, 100) as $chunk) {
            file_put_contents('php://stdout', 'Inserting chunk of '.count($chunk)." caregiver_experiences\n");
            DB::table('caregiver_experiences')->insert($chunk);
        }
    }

    // ------------------------------------------------------------------
    // Pass 6 — Caregiver References
    // ------------------------------------------------------------------

    public function passCaregiverReferences(array $hits, array $usersByBubbleId, ?\Closure $onProgress = null): void
    {
        $batch = [];
        foreach ($hits as $hit) {
            if ($onProgress) {
                $onProgress();
            }
            $source = $hit['source'];
            $role = $source['role_permissions_option_role'] ?? 'caregiver';
            if ($role !== 'caregiver' && $role !== 'caregiver_applicant') {
                continue;
            }
            $user = $usersByBubbleId[$hit['id']] ?? null;
            if (! $user) {
                continue;
            }
            $caregiver = Caregiver::where('user_id', $user->id)->first();
            if (! $caregiver) {
                continue;
            }

            $references = $source['previous_caregivers_list_text'] ?? [];
            if (is_string($references)) {
                $references = array_map('trim', explode(',', $references));
            }
            $now = now();
            foreach ((array) $references as $name) {
                if (! empty(trim($name))) {
                    $batch[] = [
                        'caregiver_id' => $caregiver->id,
                        'reference_name' => trim($name),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        if (empty($batch)) {
            return;
        }

        $cgIds = array_unique(array_column($batch, 'caregiver_id'));
        DB::table('caregiver_references')->whereIn('caregiver_id', $cgIds)->delete();
        foreach (array_chunk($batch, 100) as $chunk) {
            file_put_contents('php://stdout', 'Inserting chunk of '.count($chunk)." caregiver_references\n");
            DB::table('caregiver_references')->insert($chunk);
        }
    }

    // ------------------------------------------------------------------
    // Pass 7 — Caregiver Sponsors
    // ------------------------------------------------------------------

    public function passCaregiverSponsors(array $hits, array $usersByBubbleId, ?\Closure $onProgress = null): void
    {
        $batch = [];
        foreach ($hits as $hit) {
            if ($onProgress) {
                $onProgress();
            }
            $source = $hit['source'];
            $role = $source['role_permissions_option_role'] ?? 'caregiver';
            if ($role !== 'caregiver' && $role !== 'caregiver_applicant') {
                continue;
            }
            $user = $usersByBubbleId[$hit['id']] ?? null;
            if (! $user) {
                continue;
            }
            $caregiver = Caregiver::where('user_id', $user->id)->first();
            if (! $caregiver) {
                continue;
            }

            if (! empty($source['sponsor_email_text']) || ! empty($source['sponsor_first_name_text'])) {
                $now = now();
                $batch[] = [
                    'caregiver_id' => $caregiver->id,
                    'first_name' => $source['sponsor_first_name_text'] ?? null,
                    'last_name' => $source['sponsor_last_name_text'] ?? null,
                    'email' => $source['sponsor_email_text'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (empty($batch)) {
            return;
        }

        $cgIds = array_unique(array_column($batch, 'caregiver_id'));
        DB::table('caregiver_sponsors')->whereIn('caregiver_id', $cgIds)->delete();
        foreach (array_chunk($batch, 100) as $chunk) {
            file_put_contents('php://stdout', 'Inserting chunk of '.count($chunk)." caregiver_sponsors\n");
            DB::table('caregiver_sponsors')->insert($chunk);
        }
    }

    // ------------------------------------------------------------------
    // Pass 8 — Caregiver Certifications / Specialties / Attributes
    // ------------------------------------------------------------------

    public function passCaregiverCertifications(array $hits, array $usersByBubbleId, ?\Closure $onProgress = null): void
    {
        $certTypes = DB::table('certification_types')->pluck('id', 'name');
        $batch = [];

        foreach ($hits as $hit) {
            if ($onProgress) {
                $onProgress();
            }
            $source = $hit['source'];
            $role = $source['role_permissions_option_role'] ?? 'caregiver';
            if ($role !== 'caregiver' && $role !== 'caregiver_applicant') {
                continue;
            }
            $user = $usersByBubbleId[$hit['id']] ?? null;
            if (! $user) {
                continue;
            }
            $cgId = Caregiver::where('user_id', $user->id)->value('id');
            if (! $cgId) {
                continue;
            }

            // CPR & First Aid: use the oldest expiration date from either field
            $cprDate = self::timestampToDate($source['cpr_exp_date'] ?? null);
            $firstAidDate = self::timestampToDate($source['first_aid_exp_date'] ?? null);
            $combinedDate = $cprDate && $firstAidDate
                ? min($cprDate, $firstAidDate)
                : ($cprDate ?? $firstAidDate);

            if ($combinedDate) {
                if ($typeId = $certTypes['CPR & First Aid'] ?? null) {
                    $batch[] = ['caregiver_id' => $cgId, 'certification_type_id' => $typeId, 'expiration_date' => $combinedDate, 'verified_at' => now()];
                }
            }

            // Background Check
            if ($date = self::timestampToDate($source['background_check_exp_date'] ?? null)) {
                if ($typeId = $certTypes['Background Check'] ?? null) {
                    $batch[] = ['caregiver_id' => $cgId, 'certification_type_id' => $typeId, 'expiration_date' => $date, 'verified_at' => now()];
                }
            }

            // Trustline
            $trustline = $source['trustline__text'] ?? null;
            if (in_array(strtolower($trustline ?? ''), ['yes', 'true', '1'])) {
                if ($typeId = $certTypes['Trustline'] ?? null) {
                    $batch[] = ['caregiver_id' => $cgId, 'certification_type_id' => $typeId, 'expiration_date' => null, 'verified_at' => now()];
                }
            }
        }

        foreach ($batch as $row) {
            DB::table('caregiver_certifications')->updateOrInsert(
                ['caregiver_id' => $row['caregiver_id'], 'certification_type_id' => $row['certification_type_id']],
                ['expiration_date' => $row['expiration_date'], 'verified_at' => $row['verified_at']]
            );
        }
    }

    public function passCaregiverSpecialties(array $hits, array $usersByBubbleId, ?\Closure $onProgress = null): void
    {
        $specialtyTypes = DB::table('specialty_types')->pluck('id', 'name');
        $batch = [];

        foreach ($hits as $hit) {
            if ($onProgress) {
                $onProgress();
            }
            $source = $hit['source'];
            if (empty($source['baby_specialist_boolean'])) {
                continue;
            }
            $role = $source['role_permissions_option_role'] ?? 'caregiver';
            if ($role !== 'caregiver' && $role !== 'caregiver_applicant') {
                continue;
            }
            $user = $usersByBubbleId[$hit['id']] ?? null;
            if (! $user) {
                continue;
            }
            $cgId = Caregiver::where('user_id', $user->id)->value('id');
            if (! $cgId) {
                continue;
            }

            if ($specialtyId = $specialtyTypes['Babies'] ?? null) {
                $batch[] = ['caregiver_id' => $cgId, 'specialty_type_id' => $specialtyId];
            }
        }

        if (! empty($batch)) {
            DB::table('caregiver_specialties')->insertOrIgnore($batch);
        }
    }

    public function passCaregiverAttributes(array $hits, array $usersByBubbleId, ?\Closure $onProgress = null): void
    {
        $attrDefs = DB::table('attribute_definitions')->pluck('id', 'slug');
        $batch = [];

        foreach ($hits as $hit) {
            if ($onProgress) {
                $onProgress();
            }
            $source = $hit['source'];
            if (! isset($source['care_com_boolean'])) {
                continue;
            }
            $role = $source['role_permissions_option_role'] ?? 'caregiver';
            if ($role !== 'caregiver' && $role !== 'caregiver_applicant') {
                continue;
            }
            $user = $usersByBubbleId[$hit['id']] ?? null;
            if (! $user) {
                continue;
            }
            $cgId = Caregiver::where('user_id', $user->id)->value('id');
            if (! $cgId) {
                continue;
            }

            if ($attrId = $attrDefs['care_com'] ?? null) {
                $batch[] = [
                    'caregiver_id' => $cgId,
                    'attribute_definition_id' => $attrId,
                    'value' => $source['care_com_boolean'] ? 'true' : 'false',
                    'entity_type' => 'caregiver',
                ];
            }
        }

        foreach ($batch as $row) {
            DB::table('entity_attribute_values')->updateOrInsert(
                ['attribute_definition_id' => $row['attribute_definition_id'], 'entity_type' => $row['entity_type'], 'caregiver_id' => $row['caregiver_id']],
                ['value' => $row['value']]
            );
        }
    }

    // ------------------------------------------------------------------
    // Pass 9 — Client Addresses
    // ------------------------------------------------------------------

    public function passClientAddresses(array $hits, array $usersByBubbleId, ?\Closure $onProgress = null): void
    {
        $clientsByUserId = ClientModel::query()->get()->keyBy('user_id');
        $batch = [];
        $clientIds = [];

        foreach ($hits as $hit) {
            if ($onProgress) {
                $onProgress();
            }
            $source = $hit['source'];
            $role = $source['role_permissions_option_role'] ?? 'caregiver';
            if ($role !== 'client') {
                continue;
            }
            $user = $usersByBubbleId[$hit['id']] ?? null;
            if (! $user) {
                continue;
            }
            $client = $clientsByUserId[$user->id] ?? null;
            if (! $client) {
                continue;
            }

            $clientIds[] = $client->id;

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
                $batch[] = [
                    'client_id' => $client->id,
                    'location_type' => $locationType,
                    'label' => $label,
                    'is_primary' => true,
                    'line1' => trim(($c['street number'] ?? '').' '.($c['street'] ?? '')),
                    'city' => $c['city'] ?? 'Unknown',
                    'state' => $c['state code'] ?? 'Unknown',
                    'zip' => $c['zip code'] ?? '00000',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            $homeGeo = $source['home_address_geographic_address'] ?? null;
            if ($homeGeo && ! empty($homeGeo['components'])) {
                $line1 = trim(($homeGeo['components']['street number'] ?? '').' '.($homeGeo['components']['street'] ?? ''));
                $alreadyInBatch = collect($batch)->contains(fn ($r) => $r['client_id'] === $client->id && $r['line1'] === $line1);
                if (! $alreadyInBatch) {
                    $c = $homeGeo['components'];
                    $batch[] = [
                        'client_id' => $client->id,
                        'location_type' => $locationType,
                        'label' => $label,
                        'is_primary' => false,
                        'line1' => $line1,
                        'city' => $c['city'] ?? 'Unknown',
                        'state' => $c['state code'] ?? 'Unknown',
                        'zip' => $c['zip code'] ?? '00000',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
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
                    $alreadyInBatch = collect($batch)->contains(fn ($r) => $r['client_id'] === $client->id && $r['line1'] === $line1);
                    if ($alreadyInBatch) {
                        continue;
                    }
                    $city = $parts[1] ?? null;
                    $state = null;
                    $zip = null;
                    if (count($parts) >= 3) {
                        $stateZip = explode(' ', trim($parts[count($parts) - 2]));
                        $state = $stateZip[0] ?? null;
                        $zip = $stateZip[1] ?? null;
                    }
                    $batch[] = [
                        'client_id' => $client->id,
                        'location_type' => $locationType,
                        'label' => $label,
                        'is_primary' => false,
                        'line1' => $line1,
                        'city' => $city ?? 'Unknown',
                        'state' => $state ?? 'Unknown',
                        'zip' => $zip ?? '00000',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
        }

        if (! empty($clientIds)) {
            DB::table('client_addresses')->whereIn('client_id', array_unique($clientIds))->delete();
        }
        foreach (array_chunk($batch, 100) as $chunk) {
            file_put_contents('php://stdout', 'Inserting chunk of '.count($chunk)." client_addresses\n");
            DB::table('client_addresses')->insert($chunk);
        }
    }

    // ------------------------------------------------------------------
    // Pass 10 — Client Children
    // ------------------------------------------------------------------

    public function passClientChildren(array $hits, array $usersByBubbleId, ?\Closure $onProgress = null): void
    {
        $clientsByUserId = ClientModel::query()->get()->keyBy('user_id');
        $batch = [];
        $clientIds = [];
        $now = now();

        foreach ($hits as $hit) {
            if ($onProgress) {
                $onProgress();
            }
            $source = $hit['source'];
            $role = $source['role_permissions_option_role'] ?? 'caregiver';
            if ($role !== 'client') {
                continue;
            }
            $text = $source['names_and_ages_of_kids_text'] ?? null;
            if (! $text) {
                continue;
            }
            $user = $usersByBubbleId[$hit['id']] ?? null;
            if (! $user) {
                continue;
            }
            $client = $clientsByUserId[$user->id] ?? null;
            if (! $client) {
                continue;
            }

            $clientIds[] = $client->id;
            $normalized = preg_replace('/[\n\/;&]+/', ',', $text);
            $parts = explode(',', $normalized);

            foreach ($parts as $part) {
                $part = trim($part);
                if (! $part) {
                    continue;
                }
                $parsed = self::parseChildEntry($part);
                if ($parsed) {
                    $batch[] = [
                        'client_id' => $client->id,
                        'name' => $parsed['name'],
                        'birth_year' => $parsed['birth_year'],
                        'birth_month' => null,
                        'gender' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        if (! empty($clientIds)) {
            DB::table('client_children')->whereIn('client_id', array_unique($clientIds))->delete();
        }
        foreach (array_chunk($batch, 100) as $chunk) {
            file_put_contents('php://stdout', 'Inserting chunk of '.count($chunk)." client_children\n");
            DB::table('client_children')->insert($chunk);
        }
    }

    // ------------------------------------------------------------------
    // Static data extraction helpers
    // ------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    public static function extractCaregiverData(array $source, User $user): array
    {
        $statusName = $source['cg_status_option_cg_status_options'] ?? 'inactive';
        $statusEnum = CaregiverStatus::tryFrom($statusName) ?? CaregiverStatus::Inactive;
        $names = self::parseSourceNames($source, $user->email);

        $slug = $source['Slug'] ?? null;
        if (! $slug) {
            $slug = 'import-'.$user->bubble_id;
        } else {
            $existingWithSlug = Caregiver::where('slug', $slug)->first();
            if ($existingWithSlug && $existingWithSlug->user_id !== $user->id) {
                $slug = $slug.'-'.substr($user->bubble_id, 0, 5);
            }
        }

        $geo = $source['address_geographic_address'] ?? null;
        $addressLine1 = null;
        $addressCity = null;
        $addressState = null;
        $addressZip = null;

        if ($geo) {
            if (! empty($geo['components'])) {
                $c = $geo['components'];
                $addressLine1 = trim(($c['street number'] ?? '').' '.($c['street'] ?? ''));
                $addressCity = $c['city'] ?? null;
                $addressState = $c['state code'] ?? null;
                $addressZip = $c['zip code'] ?? null;
            }
            if (! $addressLine1 && ! empty($geo['address'])) {
                $addressLine1 = $geo['address'];
            }
        }

        return [
            'user_id' => $user->id,
            'bubble_id' => $user->bubble_id,
            'status' => $statusEnum->value,
            'first_name' => $names['first'],
            'last_name' => $names['last'],
            'slug' => $slug,
            'phone' => self::formatPhone($source['phone_text'] ?? null),
            'address_line1' => $addressLine1,
            'address_city' => $addressCity,
            'address_state' => $addressState,
            'address_zip' => $addressZip,
            'date_of_birth' => self::timestampToDate($source['date_of_birth_date'] ?? null),
            'biography' => $source['bio_text'] ?? null,
            'notes' => $source['internal_notes_text'] ?? null,
            'education_level' => $source['highest_level_education_text'] ?? null,
            'languages' => self::parseLanguages($source['languages_text'] ?? null),
            'stripe_account_id' => $source['cg_stripe_id_text'] ?? $source['stripe_account_id_text'] ?? null,
            'rating' => ($source['cg_star_rating__rated_by_client__number'] ?? 0) > 0 ? $source['cg_star_rating__rated_by_client__number'] : null,
            'admin_rating' => ! empty($source['5_star_boolean']) ? 5.0 : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function extractClientData(array $source, User $user): array
    {
        $names = self::parseSourceNames($source, $user->email);

        $bio = $source['bio_text'] ?? null;
        $houseNotes = $source['house_notes_text'] ?? null;
        if ($houseNotes) {
            $bio = $bio ? $bio."\n\n".$houseNotes : $houseNotes;
        }

        return [
            'user_id' => $user->id,
            'bubble_id' => $user->bubble_id,
            'first_name' => $names['first'],
            'last_name' => $names['last'],
            'biography' => $bio,
            'phone' => self::formatPhone($source['phone_text'] ?? null),
            'client_type' => self::mapClientType($source),
            'how_did_you_hear' => $source['how_did_you_hear_about_us_text'] ?? null,
            'notes' => $source['internal_notes_text'] ?? null,
            'stripe_customer_id' => $source['StripeCustomerID'] ?? null,
        ];
    }

    // ------------------------------------------------------------------
    // Static utility helpers
    // ------------------------------------------------------------------

    public static function parseSourceNames(array $source, string $email): array
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
            'first' => self::formatName($first),
            'last' => self::formatName($last),
        ];
    }

    public static function formatName(?string $name): string
    {
        if (! $name) {
            return '';
        }

        return (string) Str::of($name)->trim()->lower()->title();
    }

    public static function formatPhone(?string $phone): ?string
    {
        return PhoneTrait::normalizePhone($phone);
    }

    public static function parsePhotoUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        if (str_starts_with($url, '//')) {
            return 'https:'.$url;
        }

        return $url;
    }

    public static function parseLanguages(?string $value): ?string
    {
        if (! $value || strtolower(trim($value)) === 'none') {
            return null;
        }

        $languages = array_filter(array_map('trim', explode(',', $value)));

        return empty($languages) ? null : json_encode($languages);
    }

    public static function mapClientType(array $source): string
    {
        if (! empty($source['corporate__boolean'])) {
            return ClientType::Invoiced->value;
        }

        if (! empty($source['address_is_hotel__boolean'])) {
            return ClientType::Vacationer->value;
        }

        return ClientType::Resident->value;
    }

    public static function timestampToDate(?int $t): ?string
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

    public static function timestampToDateTime(?int $t): ?string
    {
        if (! $t) {
            return null;
        }
        try {
            return Carbon::createFromTimestampMs($t, 'America/Los_Angeles')
                ->setTimezone('UTC')
                ->toDateTimeString();
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function timestampToYear(mixed $v): ?int
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

    /**
     * Parse a child entry string into name and birth_year.
     *
     * @return array{name: string, birth_year: int|null}|null
     */
    protected static function parseChildEntry(string $part): ?array
    {
        $name = null;
        $age = null;

        if (preg_match('/^(.+?)\s*\((\d+)\)\s*$/u', $part, $m)) {
            $name = trim($m[1]);
            $age = (int) $m[2];
        } elseif (preg_match('/^(.+?)\s*[-–]\s*(\d+)\b/u', $part, $m)) {
            $name = trim($m[1]);
            $age = (int) $m[2];
        } elseif (preg_match('/^(.+?)\s+age\s+(\d+)\b/i', $part, $m)) {
            $name = trim($m[1]);
            $age = (int) $m[2];
        } elseif (preg_match('/^(.+?)\s+turning\s+(\d+)\b/i', $part, $m)) {
            $name = trim($m[1]);
            $age = (int) $m[2];
        } elseif (preg_match('/^(\d+)\s*(?:yr|yrs?|year|yo)\D*,\s*(.+)$/iu', $part, $m)) {
            $name = trim($m[2]);
            $age = (int) $m[1];
        } elseif (preg_match('/^(.+?)\s+(\d+)\s*(?:yr|yrs?|year|yo)\s*$/iu', $part, $m)) {
            $name = trim($m[1]);
            $age = (int) $m[2];
        } elseif (preg_match('/^(.+?),\s*(\d+)\s*$/u', $part, $m)) {
            $name = trim($m[1]);
            $age = (int) $m[2];
        } elseif (preg_match('/^(.+?)\s+(\d+)\s*$/u', $part, $m)) {
            $potentialName = trim($m[1]);
            if (preg_match('/\p{L}/u', $potentialName)) {
                $name = $potentialName;
                $age = (int) $m[2];
            }
        } elseif (preg_match('/^\s*(\p{L}+[\p{L}\s-]*\p{L}+)\s*$/u', $part, $m)) {
            $name = trim($m[1]);
        }

        if (! $name || ! preg_match('/\p{L}/u', $name)) {
            return null;
        }

        return [
            'name' => $name,
            'birth_year' => $age ? (int) date('Y') - $age : null,
        ];
    }

    // ------------------------------------------------------------------
    // Pass — Jobs (Bookings)
    // ------------------------------------------------------------------

    /**
     * Bulk import jobs as BookingGroup + Booking records.
     * Returns booking bubble_id → Booking for downstream passes.
     *
     * @param  array<int, array{source: array<string, mixed>, id: string}>  $hits
     * @return array<string, Booking>
     */
    public function passJobs(array $hits, bool $force): array
    {
        $existingBookings = Booking::whereNotNull('bubble_id')->with('bookingGroup')->get()->keyBy('bubble_id');
        $clientsByEmail = ClientModel::whereHas('user')->with('user')->get()->keyBy(fn ($c) => strtolower($c->user->email));
        $caregiversByEmail = Caregiver::whereHas('user')->with('user')->get()->keyBy(fn ($c) => strtolower($c->user->email));

        $newGroups = [];
        $newBookings = [];
        $newBookingExternalIds = [];
        $updateBookings = [];

        $i = 0;
        foreach ($hits as $hit) {
            $i++;
            if ($i % 500 === 0) {
                file_put_contents('php://stdout', "  Processing job $i/".count($hits)."\n");
            }

            $source = $hit['source'];
            $externalId = $hit['id'];

            $clientEmail = strtolower($source['client_email_text'] ?? '');
            $cgEmail = strtolower($source['cg_email_text'] ?? '');
            $client = $clientEmail ? ($clientsByEmail[$clientEmail] ?? null) : null;
            $caregiver = $cgEmail ? ($caregiversByEmail[$cgEmail] ?? null) : null;

            if (! $client) {
                continue;
            }

            if (! ($source['start_date_date'] ?? null) || ! ($source['end_date_date'] ?? null)) {
                continue;
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

            $geo = $source['street_address_geographic_address'] ?? [];
            $components = $geo['components'] ?? [];

            $negations = ['', 'no', 'none', 'na', 'other'];
            $hotelNameText = $source['hotel_name_text'] ?? null;
            $bubbleSlug = $source['address_is_hotel__option_list_of_hotels'] ?? null;

            $resolvedHotelName = null;
            if (is_string($hotelNameText) && ! in_array(strtolower($hotelNameText), $negations, true)) {
                $resolvedHotelName = $hotelNameText;
            } elseif (is_string($bubbleSlug) && ! in_array(strtolower($bubbleSlug), $negations, true)) {
                $resolvedHotelName = str_replace('_', ' ', $bubbleSlug);
            }

            $serviceType = self::mapServiceType($source['service1_option_services'] ?? 'babysitting');
            $isInvoiced = $serviceType === ServiceType::CorporateInvoiced->value || $serviceType === ServiceType::GroupChildcareInvoiced->value;

            $now = now();
            $groupData = [
                'client_id' => $client?->id,
                'submitted_at' => $now,
                'submission_type' => 'import',
                'service_type' => $serviceType,
                'location_type' => self::mapLocationType($source['address_is_hotel__option_list_of_hotels'] ?? ''),
                'address_line1' => trim(($components['street number'] ?? '').' '.($components['street'] ?? '')),
                'address_city' => $components['city'] ?? null,
                'address_state' => $components['state code'] ?? null,
                'address_zip' => $components['zip code'] ?? null,
                'hotel_id' => self::findHotelId($hotelNameText, $bubbleSlug),
                'hotel_name' => $resolvedHotelName,
                'client_first_name' => self::formatName($source['client_first_name1_text'] ?? null),
                'client_last_name' => self::formatName($source['client_last_name1_text'] ?? null),
                'client_email' => $source['client_email_text'] ?? null,
                'client_phone' => self::formatPhone($source['client_phone_text'] ?? null),
                'caregiver_notes' => $source['cg_checkout_job_notes_text'] ?? $source['caregiver_notes_text'] ?? null,
                'notes_to_sitterwise' => $source['notes_to_sw_admin_text'] ?? null,
                'admin_notes' => $source['admin_notes_text'] ?? null,
                'children' => $serviceType === ServiceType::GroupChildcareInvoiced->value
                    ? null
                    : self::parseChildren($source['names_and_ages_of_children_text'] ?? null, $source['__of_children_option_number_of_kids'] ?? null),
                'children_notes' => $serviceType === ServiceType::GroupChildcareInvoiced->value
                    ? ($source['names_and_ages_of_children_text'] ?? null)
                    : null,
                'pets' => self::parsePets($source['pets_text'] ?? null),
                'special_considerations' => self::mapSpecialConsiderations($source),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $hours = $source['total_hours_number'] ?? 0;
            $clientHourly = $source['client_job_hourly_rate_number'] ?? 0;
            $cgHourly = $source['job_cg_hourly_rate_number'] ?? 0;
            $swHourly = $source['job_agency_hourly_rate_number'] ?? 0;
            $tip = $source['cg_tip_number'] ?? 0;
            $bonus = $source['bonus_number'] ?? 0;
            $reimbursement = $source['check_out_reimbursement_number'] ?? 0;

            $chargeToClient = round($clientHourly * $hours, 2);
            $paidToCaregiver = $isInvoiced ? 0 : round($cgHourly * $hours, 2);
            $sitterwiseCut = round($swHourly * $hours, 2);

            $bookingData = [
                'bubble_id' => $externalId,
                'caregiver_id' => $caregiver?->id,
                'confirmed_at' => $caregiver ? (self::timestampToDateTime($source['confirmed_at_date'] ?? null) ?? $now) : null,
                'start_datetime' => self::timestampToDateTime($source['start_date_date'] ?? null),
                'end_datetime' => self::timestampToDateTime($source['end_date_date'] ?? null),
                'status' => $status->value,
                'total_working_hour' => $hours,
                'charge_to_client_hourly' => $clientHourly,
                'paid_to_caregiver_hourly' => $cgHourly,
                'sitterwise_cut_hourly' => $swHourly,
                'charge_to_client' => $chargeToClient,
                'paid_to_caregiver' => $paidToCaregiver,
                'sitterwise_cut' => $sitterwiseCut,
                'tip' => $tip,
                'bonus' => $bonus,
                'reimbursement' => $reimbursement,
                'reimbursement_description' => $source['check_out_reimbursement_description_text'] ?? null,
                'hotel_fee' => $source['job_hotel_booking_fee_number'] ?? 0,
                'payment_status' => $bubbleStatus === 'paid' ? 'paid' : 'unpaid',
                'stripe_payment_intent_id' => $source['payment_intent_id_text'] ?? null,
                'cancelled_at' => self::timestampToDateTime($source['cancellation_date_date'] ?? null),
                'cancellation_reason' => $source['cancellation_reason_text'] ?? null,
                'paid_to_caregiver_total' => round($cgHourly * $hours, 2) + $reimbursement + $bonus + $tip,
                'total_service_amount' => $chargeToClient + $reimbursement + $bonus,
                'total_amount' => $chargeToClient + $reimbursement + $bonus + $tip,
            ];

            // Update client bio with house notes
            if ($client && ! empty($source['house_notes_text'])) {
                $currentBio = $client->biography ?? '';
                $houseNotes = $source['house_notes_text'];
                if (! str_contains($currentBio, $houseNotes)) {
                    $client->update(['biography' => trim($currentBio."\n\nHouse Notes: ".$houseNotes)]);
                }
            }

            // Update caregiver stripe ID if missing
            if ($caregiver && ! empty($source['cg_stripe_id_text'])) {
                if (empty($caregiver->stripe_account_id)) {
                    $caregiver->update(['stripe_account_id' => $source['cg_stripe_id_text']]);
                }
            }

            $existing = $existingBookings[$externalId] ?? null;
            if ($existing) {
                if ($force) {
                    $updateBookings[$existing->id] = $bookingData;
                }
            } else {
                $newGroups[] = $groupData;
                $newBookings[] = $bookingData;
                $newBookingExternalIds[] = $externalId;
            }
        }

        file_put_contents('php://stdout', '  Loop done. New groups: '.count($newGroups).', Updates: '.count($updateBookings)."\n");

        // Bulk insert new groups
        $groupIdMap = [];
        if (! empty($newGroups)) {
            foreach (array_chunk($newGroups, 100) as $chunk) {
                file_put_contents('php://stdout', 'Inserting chunk of '.count($chunk)." booking_groups\n");
                foreach ($chunk as $j => $groupRow) {
                    foreach ($groupRow as $k => $v) {
                        if (is_array($v)) {
                            $groupRow[$k] = json_encode($v);
                        }
                    }
                    $id = DB::table('booking_groups')->insertGetId($groupRow);
                    $groupIdMap[] = $id;
                }
            }
        }

        // Assign group IDs to new bookings
        foreach ($newBookings as $j => &$bookingRow) {
            $bookingRow['booking_group_id'] = $groupIdMap[$j] ?? null;
            $bookingRow['ulid'] = (string) Str::ulid();
            $bookingRow['created_at'] = now();
            $bookingRow['updated_at'] = now();
        }
        unset($bookingRow);

        // Bulk insert new bookings
        if (! empty($newBookings)) {
            foreach (array_chunk($newBookings, 100) as $chunk) {
                file_put_contents('php://stdout', 'Inserting chunk of '.count($chunk)." bookings\n");
                DB::table('bookings')->insert($chunk);
            }
        }

        // Bulk update existing bookings
        if (! empty($updateBookings)) {
            $updateIds = array_keys($updateBookings);
            foreach (array_chunk($updateIds, 100) as $chunk) {
                foreach ($chunk as $id) {
                    DB::table('bookings')->where('id', $id)->update($updateBookings[$id]);
                }
            }
        }

        // Reload all bookings for return map + assignments
        $allBookings = Booking::whereNotNull('bubble_id')->get()->keyBy('bubble_id');

        // Create caregiver assignments for new bookings
        $assignments = [];
        foreach ($newBookings as $j => $bookingRow) {
            if (empty($bookingRow['caregiver_id'])) {
                continue;
            }
            $bubbleId = $newBookingExternalIds[$j] ?? null;
            $booking = $bubbleId ? ($allBookings[$bubbleId] ?? null) : null;
            if (! $booking) {
                continue;
            }

            $bookingStatus = BookingStatus::tryFrom($bookingRow['status']);
            $resolution = $bookingStatus === BookingStatus::Cancelled
                ? AssignmentResolution::CancelledBySitterwise
                : AssignmentResolution::Completed;

            $assignments[] = [
                'caregiver_id' => $bookingRow['caregiver_id'],
                'booking_id' => $booking->id,
                'assigned_at' => $bookingRow['confirmed_at'] ?? $bookingRow['created_at'],
                'resolution' => $resolution->value,
                'resolution_at' => $resolution === AssignmentResolution::Completed
                    ? ($bookingRow['end_datetime'] ?? $bookingRow['updated_at'])
                    : $bookingRow['updated_at'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (! empty($assignments)) {
            foreach (array_chunk($assignments, 100) as $chunk) {
                DB::table('caregiver_assignments')->insert($chunk);
            }
        }

        return $allBookings->all();
    }

    // ------------------------------------------------------------------
    // Pass — Ratings
    // ------------------------------------------------------------------

    /**
     * Bulk import ratings linked to existing bookings.
     *
     * @param  array<int, array{source: array<string, mixed>, id: string}>  $hits
     * @param  array<string, Booking>  $bookingsByBubbleId
     */
    public function passRatings(array $hits, array $bookingsByBubbleId): void
    {
        // Pre-load bookings with relationships for lookup
        $allBookings = Booking::with(['bookingGroup.client.user', 'caregiver.user'])
            ->whereNotNull('bubble_id')
            ->get()
            ->keyBy('bubble_id');

        // Index bookings by client email for fallback lookup
        $bookingsByClientEmail = [];
        foreach ($allBookings as $booking) {
            $email = strtolower($booking->bookingGroup?->client?->user?->email ?? '');
            if ($email) {
                $bookingsByClientEmail[$email][] = $booking;
            }
        }

        // Sort each client's bookings by start_datetime desc
        foreach ($bookingsByClientEmail as $email => &$clientBookings) {
            usort($clientBookings, fn ($a, $b) => strtotime($b->start_datetime) <=> strtotime($a->start_datetime));
        }
        unset($clientBookings);

        $newRatings = [];
        $errors = 0;

        foreach ($hits as $hit) {
            $source = $hit['source'];
            $externalId = $hit['id'];

            $clientEmail = strtolower($source['client_email_text'] ?? '');
            $cgEmail = strtolower($source['cg_email_text'] ?? '');
            $date = self::timestampToDateTime($source['date_date'] ?? null);
            $ratingValue = $source['number_number'] ?? 0;

            if (! $clientEmail || ! $date) {
                $errors++;

                continue;
            }

            if ($ratingValue <= 0) {
                continue;
            }

            // Find booking: try exact match by client email + date range (±5 min)
            $booking = null;
            $clientBookings = $bookingsByClientEmail[$clientEmail] ?? [];
            foreach ($clientBookings as $candidate) {
                $start = strtotime($candidate->start_datetime);
                $ratingTs = strtotime($date);
                if (abs($start - $ratingTs) <= 300) {
                    if ($cgEmail && $candidate->caregiver?->user) {
                        if (strtolower($candidate->caregiver->user->email) === $cgEmail) {
                            $booking = $candidate;
                            break;
                        }
                    } else {
                        $booking = $candidate;
                        break;
                    }
                }
            }

            // Fallback: most recent booking for this client before the date
            if (! $booking) {
                foreach ($clientBookings as $candidate) {
                    if (strtotime($candidate->start_datetime) <= strtotime($date)) {
                        $booking = $candidate;
                        break;
                    }
                }
            }

            if (! $booking) {
                $errors++;

                continue;
            }

            $raterId = null;
            $ratableId = null;
            $ratableType = null;

            if (! empty($source['review_for_client_boolean'])) {
                $raterId = $booking->caregiver?->user_id;
                $ratableId = $booking->bookingGroup?->client_id;
                $ratableType = ClientModel::class;
            } elseif (! empty($source['review_for_caregiver_boolean'])) {
                $raterId = $booking->bookingGroup?->client?->user_id;
                $ratableId = $booking->caregiver_id;
                $ratableType = Caregiver::class;
            } else {
                $raterId = $booking->bookingGroup?->client?->user_id;
                $ratableId = $booking->caregiver_id;
                $ratableType = Caregiver::class;
            }

            if (! $raterId || ! $ratableId) {
                $errors++;

                continue;
            }

            $newRatings[] = [
                'bubble_id' => $externalId,
                'booking_id' => $booking->id,
                'rater_id' => $raterId,
                'ratable_id' => $ratableId,
                'ratable_type' => $ratableType,
                'rating' => $ratingValue,
                'comment' => $source['feedback_notes_text'] ?? null,
                'created_at' => self::timestampToDateTime($source['Created Date'] ?? null) ?? now(),
                'updated_at' => now(),
            ];
        }

        file_put_contents('php://stdout', '  Ratings collected: '.count($newRatings).", Errors: $errors\n");

        if (! empty($newRatings)) {
            foreach (array_chunk($newRatings, 100) as $chunk) {
                file_put_contents('php://stdout', 'Inserting chunk of '.count($chunk)." ratings\n");
                foreach ($chunk as $row) {
                    DB::table('booking_ratings')->updateOrInsert(
                        [
                            'booking_id' => $row['booking_id'],
                            'rater_id' => $row['rater_id'],
                            'ratable_id' => $row['ratable_id'],
                            'ratable_type' => $row['ratable_type'],
                        ],
                        [
                            'bubble_id' => $row['bubble_id'],
                            'rating' => $row['rating'],
                            'comment' => $row['comment'],
                            'created_at' => $row['created_at'],
                            'updated_at' => $row['updated_at'],
                        ]
                    );
                }
            }
        }
    }

    // ------------------------------------------------------------------
    // Pass — Transactions (Client Payments + Caregiver Payouts)
    // ------------------------------------------------------------------

    /**
     * Bulk import transactions as ClientPayment + CaregiverPayout records.
     *
     * @param  array<int, array{source: array<string, mixed>, id: string}>  $hits
     * @param  array<string, Booking>  $bookingsByBubbleId
     */
    public function passTransactions(array $hits, array $bookingsByBubbleId): void
    {
        // Pre-load bookings with relationships
        $allBookings = Booking::with(['bookingGroup.client', 'caregiver'])
            ->whereNotNull('bubble_id')
            ->get();

        // Pre-build indexes for O(1) lookups
        $bookingsByPI = [];
        $bookingsByAmount = [];
        $bookingsByClientStripe = [];
        $bookingsByCgStripe = [];
        foreach ($allBookings as $b) {
            if ($b->stripe_payment_intent_id) {
                $bookingsByPI[$b->stripe_payment_intent_id] = $b;
            }
            $amt = (string) ($b->charge_to_client ?? '');
            if ($amt !== '' && $amt !== '0' && $amt !== '0.00') {
                $bookingsByAmount[$amt][] = $b;
            }
            $clientStripe = $b->bookingGroup?->client?->stripe_customer_id;
            if ($clientStripe) {
                $bookingsByClientStripe[$clientStripe][] = $b;
            }
            $cgStripe = $b->caregiver?->stripe_account_id;
            if ($cgStripe) {
                $bookingsByCgStripe[$cgStripe][] = $b;
            }
        }

        // Sort indexed bookings by start_datetime desc for fallback matching
        $sorter = fn ($a, $b) => strtotime($b->start_datetime) <=> strtotime($a->start_datetime);
        foreach ($bookingsByAmount as &$list) {
            usort($list, $sorter);
        }
        unset($list);

        $payments = [];
        $payouts = [];
        $payoutMethods = [];
        $errors = 0;

        foreach ($hits as $hit) {
            $source = $hit['source'];
            $externalId = $hit['id'];

            $pi = $source['payment_intent_id_text'] ?? null;
            $clientStripeId = $source['client_stripe_id_text'] ?? null;
            $caregiverStripeId = $source['caregiver_stripe_id_text'] ?? null;
            $date = self::timestampToDateTime($source['date_date'] ?? $source['Created Date'] ?? null);
            $amount = $source['amount_number'] ?? 0;
            $payoutAmount = $source['caregiver_total_transfer_number'] ?? 0;

            // 1. Exact match by payment intent ID
            $booking = null;
            if ($pi) {
                $booking = $bookingsByPI[$pi] ?? null;
            }

            // 2. Match by client/caregiver stripe IDs + date range (±3 days)
            if (! $booking && $date) {
                $dateTs = strtotime($date);
                $candidates = [];
                if ($clientStripeId && str_starts_with($clientStripeId, 'cus_')) {
                    $candidates = $bookingsByClientStripe[$clientStripeId] ?? [];
                } elseif ($caregiverStripeId) {
                    $candidates = $bookingsByCgStripe[$caregiverStripeId] ?? [];
                }
                foreach ($candidates as $candidate) {
                    if (abs(strtotime($candidate->start_datetime) - $dateTs) > 3 * 86400) {
                        continue;
                    }
                    $clientMatch = ! $clientStripeId || ! str_starts_with($clientStripeId, 'cus_')
                        || ($candidate->bookingGroup?->client?->stripe_customer_id === $clientStripeId);
                    $cgMatch = ! $caregiverStripeId
                        || ($candidate->caregiver?->stripe_account_id === $caregiverStripeId);
                    if ($clientMatch && $cgMatch) {
                        $booking = $candidate;
                        break;
                    }
                }
            }

            // 3. Match by amount (±7 days)
            if (! $booking && $amount > 0 && $date) {
                $dateTs = strtotime($date);
                $amountKey = (string) $amount;
                $candidates = $bookingsByAmount[$amountKey] ?? [];
                foreach ($candidates as $candidate) {
                    if (abs(strtotime($candidate->start_datetime) - $dateTs) <= 7 * 86400) {
                        $booking = $candidate;
                        break;
                    }
                }
            }

            if (! $booking) {
                $errors++;

                continue;
            }

            $payments[] = [
                'bubble_id' => $externalId,
                'booking_id' => $booking->id,
                'client_id' => $booking->bookingGroup?->client_id,
                'amount' => $amount,
                'status' => 'succeeded',
                'provider' => 'stripe',
                'provider_payment_id' => $pi,
                'paid_at' => $date,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if ($payoutAmount > 0 && $booking->caregiver) {
                $payoutMethods[$booking->caregiver_id] = [
                    'caregiver_id' => $booking->caregiver_id,
                    'provider' => 'stripe',
                    'provider_method_id' => $booking->caregiver->stripe_account_id ?? 'imported_from_bubble',
                    'account_type' => 'unknown',
                    'bank_name' => 'Imported from Bubble',
                    'last4' => '0000',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $payouts[] = [
                    'bubble_id' => $externalId,
                    'booking_id' => $booking->id,
                    'caregiver_id' => $booking->caregiver_id,
                    'amount' => $payoutAmount,
                    'status' => 'paid',
                    'payout_date' => $date,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        file_put_contents('php://stdout', '  Payments: '.count($payments).', Payouts: '.count($payouts).", Errors: $errors\n");

        // Ensure payout methods exist
        foreach ($payoutMethods as $methodData) {
            DB::table('caregiver_payout_methods')->updateOrInsert(
                ['caregiver_id' => $methodData['caregiver_id'], 'provider' => 'stripe'],
                $methodData
            );
        }

        // Get payout method IDs for payouts
        $payoutMethodIds = DB::table('caregiver_payout_methods')
            ->where('provider', 'stripe')
            ->pluck('id', 'caregiver_id');

        foreach ($payouts as &$payout) {
            $payout['caregiver_payout_method_id'] = $payoutMethodIds[$payout['caregiver_id']] ?? null;
        }
        unset($payout);

        // Bulk insert payments
        if (! empty($payments)) {
            foreach (array_chunk($payments, 100) as $chunk) {
                file_put_contents('php://stdout', 'Inserting chunk of '.count($chunk)." client_payments\n");
                foreach ($chunk as $row) {
                    DB::table('client_payments')->updateOrInsert(
                        ['bubble_id' => $row['bubble_id']],
                        $row
                    );
                }
            }
        }

        // Bulk insert payouts
        if (! empty($payouts)) {
            foreach (array_chunk($payouts, 100) as $chunk) {
                file_put_contents('php://stdout', 'Inserting chunk of '.count($chunk)." caregiver_payouts\n");
                foreach ($chunk as $row) {
                    if (! $row['caregiver_payout_method_id']) {
                        continue;
                    }
                    DB::table('caregiver_payouts')->updateOrInsert(
                        ['bubble_id' => $row['bubble_id']],
                        $row
                    );
                }
            }
        }
    }

    // ------------------------------------------------------------------
    // Shared helpers for Jobs/Ratings/Transactions
    // ------------------------------------------------------------------

    protected static function mapServiceType(?string $bubbleService): string
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

    protected static function mapLocationType(?string $hotelOption): string
    {
        if (! $hotelOption || strtolower($hotelOption) === 'no') {
            return LocationType::PrivateHome->value;
        }

        return LocationType::Hotel->value;
    }

    protected static function mapSpecialConsiderations(array $source): array
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

        $notes = strtolower(($source['special_considerations1_text'] ?? '').' '.($source['special_considerations_text'] ?? ''));
        if (str_contains($notes, 'infant')) {
            $considerations[] = SpecialConsideration::InfantCare->value;
        }

        $pets = self::parsePets($source['pets_text'] ?? null);
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

    protected static function parseChildren(?string $text, ?string $countStr): ?array
    {
        if (! $text && ! $countStr) {
            return null;
        }

        $children = [];
        $count = (int) str_replace('_', '', $countStr ?? '1');

        if ($text) {
            $parts = preg_split('/[,;]/', $text);
            foreach ($parts as $part) {
                if (trim($part)) {
                    $children[] = ['name' => trim($part)];
                }
            }
        }

        while (count($children) < $count) {
            $children[] = ['name' => 'Child '.(count($children) + 1)];
        }

        return $children;
    }

    protected static function parsePets(?string $text): ?array
    {
        if (! $text) {
            return null;
        }

        $lower = strtolower($text);
        if (in_array(trim($lower), ['no', 'none', 'n/a', 'na', 'no pets', 'no pet', 'no animals', 'none at this time'])) {
            return null;
        }

        $pets = [];
        if (str_contains($lower, 'dog')) {
            $pets[] = ['type' => 'dog', 'notes' => $text];
        }
        if (str_contains($lower, 'cat')) {
            $pets[] = ['type' => 'cat', 'notes' => $text];
        }

        return empty($pets) ? [['type' => 'other', 'notes' => $text]] : $pets;
    }

    protected static function findHotelId(?string $hotelName, ?string $bubbleSlug): ?int
    {
        $negations = ['', 'no', 'none', 'na', 'other'];
        $name = null;

        if ($hotelName && ! in_array(strtolower($hotelName), $negations, true)) {
            $name = $hotelName;
        } elseif ($bubbleSlug && ! in_array(strtolower($bubbleSlug), $negations, true)) {
            $name = str_replace('_', ' ', $bubbleSlug);
        }

        if ($name === null) {
            return null;
        }

        $normalized = self::normalizeHotelName($name);
        $hotels = Hotel::all();

        foreach ($hotels as $hotel) {
            if (self::normalizeHotelName($hotel->name) === $normalized) {
                return $hotel->id;
            }
        }

        foreach ($hotels as $hotel) {
            if (levenshtein($normalized, self::normalizeHotelName($hotel->name)) <= 2) {
                return $hotel->id;
            }
        }

        foreach ($hotels as $hotel) {
            $hotelNorm = self::normalizeHotelName($hotel->name);
            if (str_contains($hotelNorm, $normalized) || str_contains($normalized, $hotelNorm)) {
                return $hotel->id;
            }
        }

        return null;
    }

    protected static function normalizeHotelName(string $name): string
    {
        $name = strtolower(trim($name));
        $name = str_replace([' - ', ' & ', ' and ', ' by ', ' the ', ' at '], ' ', $name);
        $name = str_replace(['-', ',', '.', "\u{2019}", "'"], '', $name);
        $name = preg_replace('/\b(the|hotel|resort|spa|inn|suites)\b/', '', $name);
        $name = preg_replace('/\s+/', ' ', $name);

        return trim($name);
    }

    public function finalizeCaregiverSlugs(): int
    {
        $caregivers = Caregiver::where('slug', 'like', 'import-%')->get();
        $updated = 0;

        foreach ($caregivers as $caregiver) {
            $firstName = Str::slug($caregiver->first_name ?? '');
            $lastInitial = $caregiver->last_name
                ? Str::slug(mb_substr($caregiver->last_name, 0, 1))
                : '';

            $baseSlug = $firstName.'-'.$lastInitial;

            if (empty($baseSlug) || $baseSlug === '-') {
                $baseSlug = Str::slug("{$caregiver->first_name} {$caregiver->last_name}");
            }

            if (empty($baseSlug)) {
                $baseSlug = 'caregiver';
            }

            $originalSlug = $baseSlug;
            $counter = 2;

            while (Caregiver::where('slug', $baseSlug)->where('id', '!=', $caregiver->id)->exists()) {
                $baseSlug = $originalSlug.'-'.$counter;
                $counter++;
            }

            $caregiver->update(['slug' => $baseSlug]);
            $updated++;
        }

        return $updated;
    }
}
