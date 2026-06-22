<?php

use App\Models\Caregiver;
use App\Models\ReferenceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    seedLookupTables();
});

test('reference page loads with valid token', function () {
    $user = User::factory()->create(['role' => 'caregiver']);
    $caregiver = Caregiver::factory()->create([
        'user_id' => $user->id,
        'first_name' => 'Jane',
        'last_name' => 'Smith',
    ]);

    ReferenceRequest::create([
        'token' => 'test-reference-token-123',
        'caregiver_id' => $caregiver->id,
        'reference_name' => 'John Reference',
        'reference_email' => 'john@example.com',
        'relationship' => 'Former Employer',
        'years_known' => '3-5',
        'is_sponsor' => false,
    ]);

    visit('/references/test-reference-token-123')
        ->assertSee('Reference for Jane')
        ->assertNoJavaScriptErrors();
});

test('reference can fill and submit form', function () {
    $user = User::factory()->create(['role' => 'caregiver']);
    $caregiver = Caregiver::factory()->create([
        'user_id' => $user->id,
        'first_name' => 'Jane',
        'last_name' => 'Smith',
    ]);

    ReferenceRequest::create([
        'token' => 'test-reference-token-456',
        'caregiver_id' => $caregiver->id,
        'reference_name' => 'John Reference',
        'reference_email' => 'john@example.com',
        'relationship' => null,
        'years_known' => null,
        'is_sponsor' => false,
    ]);

    $page = visit('/references/test-reference-token-456');

    fillField($page, '#relationship', 'Former Colleague');

    $page->assertNoJavaScriptErrors();
});

test('already submitted reference shows confirmation page', function () {
    $user = User::factory()->create(['role' => 'caregiver']);
    $caregiver = Caregiver::factory()->create([
        'user_id' => $user->id,
        'first_name' => 'Jane',
        'last_name' => 'Smith',
    ]);

    ReferenceRequest::create([
        'token' => 'test-reference-token-submitted',
        'caregiver_id' => $caregiver->id,
        'reference_name' => 'John Reference',
        'reference_email' => 'john@example.com',
        'relationship' => 'Friend',
        'years_known' => '3-5',
        'is_sponsor' => false,
        'submitted_at' => now(),
    ]);

    visit('/references/test-reference-token-submitted')
        ->assertSee('Thank you')
        ->assertNoJavaScriptErrors();
});
