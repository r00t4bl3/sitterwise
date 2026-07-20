<?php

use App\Enums\CaregiverStatus;
use App\Models\Caregiver;
use App\Models\CaregiverAgreement;
use App\Models\CertificationType;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function caregiverWithAgreement(?string $pdfPath = null): array
{
    $user = User::factory()->create(['role' => 'caregiver']);
    $caregiver = Caregiver::create([
        'user_id' => $user->id,
        'status' => CaregiverStatus::Active->value,
        'first_name' => 'Agree',
        'last_name' => 'Signer',
        'slug' => 'agree-signer-'.Str::random(4),
        'phone' => '555-0100',
        'date_of_birth' => '1990-01-01',
    ]);

    $pdfPath ??= "agreements/{$caregiver->id}/agreement.pdf";
    Storage::disk('documents')->put($pdfPath, '%PDF-fake');

    $agreement = CaregiverAgreement::create([
        'caregiver_id' => $caregiver->id,
        'type' => 'agreement',
        'pdf_path' => $pdfPath,
        'signed_at' => now(),
    ]);

    return [$caregiver, $agreement];
}

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

describe('Caregiver agreement download', function () {
    it('lets an admin stream the agreement', function () {
        [$caregiver, $agreement] = caregiverWithAgreement();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('caregivers.agreements.download', [$caregiver, $agreement]))
            ->assertOk();
    });

    it('forbids a client and a caregiver, redirects a guest', function () {
        [$caregiver, $agreement] = caregiverWithAgreement();

        $clientUser = User::factory()->create(['role' => 'client']);
        Client::factory()->create(['user_id' => $clientUser->id]);
        $this->actingAs($clientUser)
            ->get(route('caregivers.agreements.download', [$caregiver, $agreement]))
            ->assertForbidden();

        $otherCaregiver = User::factory()->create(['role' => 'caregiver']);
        $this->actingAs($otherCaregiver)
            ->get(route('caregivers.agreements.download', [$caregiver, $agreement]))
            ->assertForbidden();

        auth()->logout();
        $this->get(route('caregivers.agreements.download', [$caregiver, $agreement]))
            ->assertRedirect(route('login'));
    });

    it('404s when the agreement belongs to a different caregiver', function () {
        [$caregiver, $agreement] = caregiverWithAgreement();
        $otherUser = User::factory()->create(['role' => 'caregiver']);
        $otherCaregiver = Caregiver::create([
            'user_id' => $otherUser->id,
            'status' => CaregiverStatus::Active->value,
            'first_name' => 'Other',
            'last_name' => 'Caregiver',
            'slug' => 'other-cg-'.Str::random(4),
            'phone' => '555-0101',
            'date_of_birth' => '1990-01-01',
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('caregivers.agreements.download', [$otherCaregiver, $agreement]))
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

    it('moves a legacy absolute-path agreement onto the private disk and rewrites pdf_path', function () {
        $user = User::factory()->create(['role' => 'caregiver']);
        $caregiver = Caregiver::create([
            'user_id' => $user->id,
            'status' => CaregiverStatus::Active->value,
            'first_name' => 'Legacy',
            'last_name' => 'Agreement',
            'slug' => 'legacy-agr-'.Str::random(4),
            'phone' => '555-0004',
            'date_of_birth' => '1990-01-01',
        ]);

        // Simulate the old file_put_contents(storage_path(...)) absolute path.
        $absolute = storage_path("app/agreements/{$caregiver->id}/agreement.pdf");
        @mkdir(dirname($absolute), 0755, true);
        file_put_contents($absolute, '%PDF-legacy');

        $agreement = CaregiverAgreement::create([
            'caregiver_id' => $caregiver->id,
            'type' => 'agreement',
            'pdf_path' => $absolute,
            'signed_at' => now(),
        ]);

        $this->artisan('documents:migrate-to-private --apply')->assertSuccessful();

        $relative = "agreements/{$caregiver->id}/agreement.pdf";
        expect($agreement->fresh()->pdf_path)->toBe($relative);
        Storage::disk('documents')->assertExists($relative);
        expect(is_file($absolute))->toBeFalse();
    });

    it('resolves a stale absolute path from a renamed deployment via the current storage location', function () {
        $user = User::factory()->create(['role' => 'caregiver']);
        $caregiver = Caregiver::create([
            'user_id' => $user->id,
            'status' => CaregiverStatus::Active->value,
            'first_name' => 'Moved',
            'last_name' => 'Deployment',
            'slug' => 'moved-deploy-'.Str::random(4),
            'phone' => '555-0005',
            'date_of_birth' => '1990-01-01',
        ]);

        // The DB stores an absolute path from the OLD deployment directory that
        // no longer exists on disk...
        $stalePath = "/var/www/html/old-domain.example/storage/app/agreements/{$caregiver->id}/agreement.pdf";
        // ...but the file actually lives under THIS deployment's storage.
        $relative = "agreements/{$caregiver->id}/agreement.pdf";
        $current = storage_path('app/'.$relative);
        @mkdir(dirname($current), 0755, true);
        file_put_contents($current, '%PDF-moved');

        $agreement = CaregiverAgreement::create([
            'caregiver_id' => $caregiver->id,
            'type' => 'agreement',
            'pdf_path' => $stalePath,
            'signed_at' => now(),
        ]);

        $this->artisan('documents:migrate-to-private --apply')->assertSuccessful();

        expect($agreement->fresh()->pdf_path)->toBe($relative);
        Storage::disk('documents')->assertExists($relative);
        expect(Storage::disk('documents')->get($relative))->toBe('%PDF-moved');
        expect(is_file($current))->toBeFalse();
    });
});
