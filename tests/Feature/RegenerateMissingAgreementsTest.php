<?php

use App\Enums\CaregiverStatus;
use App\Models\Caregiver;
use App\Models\CaregiverAgreement;
use App\Models\CaregiverApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function caregiverWithApplication(array $overrides = []): Caregiver
{
    $user = User::factory()->create(['role' => 'caregiver']);
    $caregiver = Caregiver::create(array_merge([
        'user_id' => $user->id,
        'status' => CaregiverStatus::Active->value,
        'first_name' => 'Reg',
        'last_name' => 'Gen',
        'slug' => 'reg-gen-'.Str::random(4),
        'phone' => '555-0200',
        'date_of_birth' => '1990-01-01',
    ], $overrides));

    CaregiverApplication::create([
        'caregiver_id' => $caregiver->id,
        'data' => [
            'personal' => ['first_name' => 'Reg', 'last_name' => 'Gen'],
            'verification' => ['signature' => 'Reg Gen'],
            'agreement' => ['signature' => 'Reg Gen'],
        ],
        'submitted_at' => now()->subMonths(3),
    ]);

    return $caregiver;
}

beforeEach(function () {
    Storage::fake('documents');
});

describe('agreements:regenerate-missing', function () {
    it('regenerates a missing agreement from application data and dates it from the submission', function () {
        $caregiver = caregiverWithApplication();
        $submittedAt = $caregiver->application->submitted_at;

        // A missing agreement: pdf_path points nowhere retrievable.
        $agreement = CaregiverAgreement::create([
            'caregiver_id' => $caregiver->id,
            'type' => 'agreement',
            'pdf_path' => '/var/www/html/old-deploy/storage/app/agreements/999/agreement.pdf',
            'signed_at' => now(),
        ]);

        $this->artisan('agreements:regenerate-missing --apply')->assertSuccessful();

        $relative = "agreements/{$caregiver->id}/agreement.pdf";
        $agreement->refresh();

        expect($agreement->pdf_path)->toBe($relative);
        Storage::disk('documents')->assertExists($relative);
        expect(Storage::disk('documents')->get($relative))->toStartWith('%PDF');
        expect($agreement->signed_at->toDateString())->toBe($submittedAt->toDateString());
    });

    it('leaves an already-present agreement untouched', function () {
        $caregiver = caregiverWithApplication();
        $relative = "agreements/{$caregiver->id}/verification.pdf";
        Storage::disk('documents')->put($relative, '%PDF-original');

        $agreement = CaregiverAgreement::create([
            'caregiver_id' => $caregiver->id,
            'type' => 'verification',
            'pdf_path' => $relative,
            'signed_at' => now(),
        ]);

        $this->artisan('agreements:regenerate-missing --apply')->assertSuccessful();

        // Untouched — still the original bytes.
        expect(Storage::disk('documents')->get($relative))->toBe('%PDF-original');
    });

    it('skips an agreement whose caregiver has no application data', function () {
        $user = User::factory()->create(['role' => 'caregiver']);
        $caregiver = Caregiver::create([
            'user_id' => $user->id,
            'status' => CaregiverStatus::Active->value,
            'first_name' => 'No',
            'last_name' => 'App',
            'slug' => 'no-app-'.Str::random(4),
            'phone' => '555-0201',
            'date_of_birth' => '1990-01-01',
        ]);

        $agreement = CaregiverAgreement::create([
            'caregiver_id' => $caregiver->id,
            'type' => 'agreement',
            'pdf_path' => '/var/www/html/old/storage/app/agreements/1/agreement.pdf',
            'signed_at' => now(),
        ]);

        $this->artisan('agreements:regenerate-missing --apply')->assertSuccessful();

        // Path unchanged; nothing written.
        expect($agreement->fresh()->pdf_path)->toBe('/var/www/html/old/storage/app/agreements/1/agreement.pdf');
        Storage::disk('documents')->assertMissing("agreements/{$caregiver->id}/agreement.pdf");
    });

    it('is a no-op dry run without --apply', function () {
        $caregiver = caregiverWithApplication();

        CaregiverAgreement::create([
            'caregiver_id' => $caregiver->id,
            'type' => 'agreement',
            'pdf_path' => '/var/www/html/old/storage/app/agreements/5/agreement.pdf',
            'signed_at' => now(),
        ]);

        $this->artisan('agreements:regenerate-missing')->assertSuccessful();

        Storage::disk('documents')->assertMissing("agreements/{$caregiver->id}/agreement.pdf");
    });
});
