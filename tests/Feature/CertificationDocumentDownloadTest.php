<?php

use App\Enums\CaregiverStatus;
use App\Models\Caregiver;
use App\Models\CertificationType;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function caregiverWithCertDocument(): array
{
    $user = User::factory()->create(['role' => 'caregiver']);
    $caregiver = Caregiver::create([
        'user_id' => $user->id,
        'status' => CaregiverStatus::Active->value,
        'first_name' => 'Doc',
        'last_name' => 'Holder',
        'slug' => 'doc-holder-'.Str::random(4),
        'phone' => '555-0000',
        'date_of_birth' => '1990-01-01',
    ]);

    $certType = CertificationType::factory()->create(['name' => 'CPR']);

    $path = UploadedFile::fake()->create('cpr.pdf', 20)->store('cpr-cards', 'documents');

    $caregiver->certifications()->attach($certType->id, [
        'file_path' => $path,
        'expiration_date' => now()->addYear(),
    ]);

    return [$caregiver, $certType, $path];
}

beforeEach(function () {
    Storage::fake('documents');
});

describe('Certification document download', function () {
    it('lets an admin stream the document', function () {
        [$caregiver, $certType] = caregiverWithCertDocument();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('caregivers.certifications.document', [$caregiver, $certType]))
            ->assertOk();
    });

    it('lets a super admin stream the document', function () {
        [$caregiver, $certType] = caregiverWithCertDocument();
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($superAdmin)
            ->get(route('caregivers.certifications.document', [$caregiver, $certType]))
            ->assertOk();
    });

    it('forbids a client', function () {
        [$caregiver, $certType] = caregiverWithCertDocument();
        $clientUser = User::factory()->create(['role' => 'client']);
        Client::factory()->create(['user_id' => $clientUser->id]);

        $this->actingAs($clientUser)
            ->get(route('caregivers.certifications.document', [$caregiver, $certType]))
            ->assertForbidden();
    });

    it('forbids another caregiver', function () {
        [$caregiver, $certType] = caregiverWithCertDocument();
        $otherCaregiverUser = User::factory()->create(['role' => 'caregiver']);

        $this->actingAs($otherCaregiverUser)
            ->get(route('caregivers.certifications.document', [$caregiver, $certType]))
            ->assertForbidden();
    });

    it('redirects a guest to login', function () {
        [$caregiver, $certType] = caregiverWithCertDocument();

        $this->get(route('caregivers.certifications.document', [$caregiver, $certType]))
            ->assertRedirect(route('login'));
    });

    it('404s when the certification has no document', function () {
        $user = User::factory()->create(['role' => 'caregiver']);
        $caregiver = Caregiver::create([
            'user_id' => $user->id,
            'status' => CaregiverStatus::Active->value,
            'first_name' => 'No',
            'last_name' => 'Doc',
            'slug' => 'no-doc-'.Str::random(4),
            'phone' => '555-0001',
            'date_of_birth' => '1990-01-01',
        ]);
        $certType = CertificationType::factory()->create(['name' => 'Trustline']);
        $caregiver->certifications()->attach($certType->id, ['file_path' => null]);

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('caregivers.certifications.document', [$caregiver, $certType]))
            ->assertNotFound();
    });
});

describe('documents:migrate-to-private command', function () {
    it('moves an existing public document to the private disk', function () {
        Storage::fake('public');

        $user = User::factory()->create(['role' => 'caregiver']);
        $caregiver = Caregiver::create([
            'user_id' => $user->id,
            'status' => CaregiverStatus::Active->value,
            'first_name' => 'Legacy',
            'last_name' => 'File',
            'slug' => 'legacy-file-'.Str::random(4),
            'phone' => '555-0002',
            'date_of_birth' => '1990-01-01',
        ]);
        $certType = CertificationType::factory()->create(['name' => 'CPR']);

        Storage::disk('public')->put('certifications/legacy.pdf', 'legacy-bytes');
        $caregiver->certifications()->attach($certType->id, ['file_path' => 'certifications/legacy.pdf']);

        $this->artisan('documents:migrate-to-private --apply')->assertSuccessful();

        Storage::disk('documents')->assertExists('certifications/legacy.pdf');
        Storage::disk('public')->assertMissing('certifications/legacy.pdf');
        expect(Storage::disk('documents')->get('certifications/legacy.pdf'))->toBe('legacy-bytes');
    });

    it('is a no-op dry run without --apply', function () {
        Storage::fake('public');
        Storage::disk('public')->put('certifications/dry.pdf', 'x');

        $user = User::factory()->create(['role' => 'caregiver']);
        $caregiver = Caregiver::create([
            'user_id' => $user->id,
            'status' => CaregiverStatus::Active->value,
            'first_name' => 'Dry',
            'last_name' => 'Run',
            'slug' => 'dry-run-'.Str::random(4),
            'phone' => '555-0003',
            'date_of_birth' => '1990-01-01',
        ]);
        $certType = CertificationType::factory()->create(['name' => 'CPR']);
        $caregiver->certifications()->attach($certType->id, ['file_path' => 'certifications/dry.pdf']);

        $this->artisan('documents:migrate-to-private')->assertSuccessful();

        Storage::disk('public')->assertExists('certifications/dry.pdf');
        Storage::disk('documents')->assertMissing('certifications/dry.pdf');
    });
});
