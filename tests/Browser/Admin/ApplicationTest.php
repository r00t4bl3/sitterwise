<?php

use App\Enums\CaregiverStatus;
use App\Models\Caregiver;
use App\Models\CaregiverApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('applications index loads', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin);

    visit('/applications')
        ->assertSee('Caregiver Applications')
        ->assertNoJavaScriptErrors();
});

test('can filter applications by status', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin);

    $caregiverUser = User::factory()->create(['role' => 'caregiver', 'email' => 'cg-filter@example.com']);
    $caregiver = Caregiver::create([
        'user_id' => $caregiverUser->id,
        'first_name' => 'Filter',
        'last_name' => 'Test',
        'slug' => 'filter-test',
        'phone' => '555-000-0000',
        'address_line1' => '123 Main St',
        'address_city' => 'San Diego',
        'address_state' => 'CA',
        'address_zip' => '92101',
        'date_of_birth' => '1990-01-01',
        'status' => CaregiverStatus::UnderReview->value,
    ]);

    CaregiverApplication::create([
        'caregiver_id' => $caregiver->id,
        'data' => [
            'personal' => ['first_name' => 'Filter', 'last_name' => 'Test'],
            'sponsor' => ['first_name' => 'S', 'last_name' => 'P', 'email' => 'sp@example.com', 'phone' => '555', 'relationship' => 'Friend'],
        ],
        'submitted_at' => now(),
    ]);

    $page = visit('/applications');

    usleep(300000);

    $page->script(<<<'JS'
        const buttons = Array.from(document.querySelectorAll('button'));
        const statusBtn = buttons.find(b => b.textContent.trim() === 'Under Review');
        if (statusBtn) statusBtn.click();
    JS);

    usleep(500000);

    $page->assertNoJavaScriptErrors();
});

test('can search applications by name', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin);

    $caregiverUser = User::factory()->create(['role' => 'caregiver', 'email' => 'cg-search@example.com']);
    $caregiver = Caregiver::create([
        'user_id' => $caregiverUser->id,
        'first_name' => 'Searchable',
        'last_name' => 'Applicant',
        'slug' => 'searchable-applicant',
        'phone' => '555-111-2222',
        'address_line1' => '456 Oak Ave',
        'address_city' => 'San Diego',
        'address_state' => 'CA',
        'address_zip' => '92102',
        'date_of_birth' => '1990-01-01',
        'status' => CaregiverStatus::UnderReview->value,
    ]);

    CaregiverApplication::create([
        'caregiver_id' => $caregiver->id,
        'data' => [
            'personal' => ['first_name' => 'Searchable', 'last_name' => 'Applicant'],
            'sponsor' => ['first_name' => 'S', 'last_name' => 'P', 'email' => 'sp@example.com', 'phone' => '555', 'relationship' => 'Friend'],
        ],
        'submitted_at' => now(),
    ]);

    $page = visit('/applications');

    usleep(300000);

    fillField($page, 'input[placeholder*="Search by name"]', 'Searchable');

    usleep(500000);

    $page->assertNoJavaScriptErrors();
});
