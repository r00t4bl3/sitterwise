<?php

use App\Enums\CaregiverStatus;
use App\Models\Caregiver;
use App\Models\ReferenceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('status page loads with valid token', function () {
    $token = 'test-status-token-'.time();

    $user = User::factory()->create(['role' => 'caregiver']);

    $caregiver = Caregiver::create([
        'user_id' => $user->id,
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'slug' => 'jane-smith-status',
        'phone' => '555-123-4567',
        'address_city' => 'San Diego',
        'address_state' => 'CA',
        'address_zip' => '92101',
        'date_of_birth' => '1990-01-01',
        'status' => CaregiverStatus::Applicant->value,
        'status_token' => $token,
    ]);

    $referenceRequest = ReferenceRequest::create([
        'caregiver_id' => $caregiver->id,
        'reference_name' => 'Reference One',
        'reference_email' => 'ref1@example.com',
        'relationship' => 'Friend',
        'is_sponsor' => false,
        'token' => Str::random(32),
    ]);

    $page = visit('/caregiver/apply/status/'.$token);

    $page->assertSee('Application Status')
        ->assertSee('Jane Smith')
        ->assertSee('Applicant')
        ->assertSee('Reference One')
        ->assertNoJavaScriptErrors();
});

test('invalid token shows error', function () {
    $page = visit('/caregiver/apply/status/invalid-token-that-does-not-exist');

    $page->assertSee('404')
        ->assertNoJavaScriptErrors();
});
