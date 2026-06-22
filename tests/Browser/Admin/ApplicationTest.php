<?php

use App\Enums\CaregiverStatus;
use App\Models\Caregiver;
use App\Models\CaregiverApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    seedLookupTables();
});

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

test('application detail page shows sections', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin);

    $caregiverUser = User::factory()->create(['role' => 'caregiver', 'email' => 'cg-detail@example.com']);
    $caregiver = Caregiver::create([
        'user_id' => $caregiverUser->id,
        'first_name' => 'Detail',
        'last_name' => 'Test',
        'slug' => 'detail-test',
        'phone' => '555-333-4444',
        'address_line1' => '789 Pine St',
        'address_city' => 'San Diego',
        'address_state' => 'CA',
        'address_zip' => '92103',
        'date_of_birth' => '1990-01-01',
        'status' => CaregiverStatus::Applicant->value,
    ]);

    $application = CaregiverApplication::create([
        'caregiver_id' => $caregiver->id,
        'data' => [
            'personal' => ['first_name' => 'Detail', 'last_name' => 'Test'],
            'sponsor' => ['first_name' => 'S', 'last_name' => 'P', 'email' => 'sp@example.com', 'phone' => '555', 'relationship' => 'Friend'],
        ],
        'submitted_at' => now(),
    ]);

    $page = visit('/applications/'.$application->id);

    $page->assertSee('Applicant Information')
        ->assertSee('Status')
        ->assertSee('Approve')
        ->assertSee('Decline')
        ->assertNoJavaScriptErrors();
});

test('application shows references section', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin);

    $caregiverUser = User::factory()->create(['role' => 'caregiver', 'email' => 'cg-ref@example.com']);
    $caregiver = Caregiver::create([
        'user_id' => $caregiverUser->id,
        'first_name' => 'Ref',
        'last_name' => 'Test',
        'slug' => 'ref-test',
        'phone' => '555-555-5555',
        'address_line1' => '321 Oak St',
        'address_city' => 'San Diego',
        'address_state' => 'CA',
        'address_zip' => '92104',
        'date_of_birth' => '1990-01-01',
        'status' => CaregiverStatus::Applicant->value,
    ]);

    $application = CaregiverApplication::create([
        'caregiver_id' => $caregiver->id,
        'data' => [
            'personal' => ['first_name' => 'Ref', 'last_name' => 'Test'],
            'sponsor' => ['first_name' => 'S', 'last_name' => 'P', 'email' => 'sp@example.com', 'phone' => '555', 'relationship' => 'Friend'],
        ],
        'submitted_at' => now(),
    ]);

    $page = visit('/applications/'.$application->id);

    usleep(300000);

    $page->assertSee('References')
        ->assertNoJavaScriptErrors();
});

test('application approve button shows confirm dialog', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin);

    $caregiverUser = User::factory()->create(['role' => 'caregiver', 'email' => 'cg-approve@example.com']);
    $caregiver = Caregiver::create([
        'user_id' => $caregiverUser->id,
        'first_name' => 'Approve',
        'last_name' => 'Test',
        'slug' => 'approve-test',
        'phone' => '555-666-7777',
        'address_line1' => '654 Elm St',
        'address_city' => 'San Diego',
        'address_state' => 'CA',
        'address_zip' => '92105',
        'date_of_birth' => '1990-01-01',
        'status' => CaregiverStatus::Applicant->value,
    ]);

    $application = CaregiverApplication::create([
        'caregiver_id' => $caregiver->id,
        'data' => [
            'personal' => ['first_name' => 'Approve', 'last_name' => 'Test'],
            'sponsor' => ['first_name' => 'S', 'last_name' => 'P', 'email' => 'sp@example.com', 'phone' => '555', 'relationship' => 'Friend'],
        ],
        'submitted_at' => now(),
    ]);

    $page = visit('/applications/'.$application->id);

    usleep(300000);

    $page->script(<<<'JS'
        const btns = Array.from(document.querySelectorAll('button'));
        const approveBtn = btns.find(b => b.textContent.trim() === 'Approve');
        if (approveBtn) approveBtn.click();
    JS);

    usleep(500000);

    $page->assertSee('Move this application to Under Review')
        ->assertNoJavaScriptErrors();
});

test('application decline button shows confirm dialog', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin);

    $caregiverUser = User::factory()->create(['role' => 'caregiver', 'email' => 'cg-decline@example.com']);
    $caregiver = Caregiver::create([
        'user_id' => $caregiverUser->id,
        'first_name' => 'Decline',
        'last_name' => 'Test',
        'slug' => 'decline-test',
        'phone' => '555-777-8888',
        'address_line1' => '987 Maple St',
        'address_city' => 'San Diego',
        'address_state' => 'CA',
        'address_zip' => '92106',
        'date_of_birth' => '1990-01-01',
        'status' => CaregiverStatus::Applicant->value,
    ]);

    $application = CaregiverApplication::create([
        'caregiver_id' => $caregiver->id,
        'data' => [
            'personal' => ['first_name' => 'Decline', 'last_name' => 'Test'],
            'sponsor' => ['first_name' => 'S', 'last_name' => 'P', 'email' => 'sp@example.com', 'phone' => '555', 'relationship' => 'Friend'],
        ],
        'submitted_at' => now(),
    ]);

    $page = visit('/applications/'.$application->id);

    usleep(300000);

    $page->script(<<<'JS'
        const btns = Array.from(document.querySelectorAll('button'));
        const declineBtn = btns.find(b => b.textContent.trim() === 'Decline');
        if (declineBtn) declineBtn.click();
    JS);

    usleep(500000);

    $page->assertSee('Decline this application')
        ->assertNoJavaScriptErrors();
});

test('application interview schedule button shows confirm dialog', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin);

    $caregiverUser = User::factory()->create(['role' => 'caregiver', 'email' => 'cg-int-sched@example.com']);
    $caregiver = Caregiver::create([
        'user_id' => $caregiverUser->id,
        'first_name' => 'Interview',
        'last_name' => 'Schedule',
        'slug' => 'interview-schedule',
        'phone' => '555-888-9999',
        'address_line1' => '111 Cedar St',
        'address_city' => 'San Diego',
        'address_state' => 'CA',
        'address_zip' => '92107',
        'date_of_birth' => '1990-01-01',
        'status' => CaregiverStatus::UnderReview->value,
    ]);

    $application = CaregiverApplication::create([
        'caregiver_id' => $caregiver->id,
        'data' => [
            'personal' => ['first_name' => 'Interview', 'last_name' => 'Schedule'],
            'sponsor' => ['first_name' => 'S', 'last_name' => 'P', 'email' => 'sp@example.com', 'phone' => '555', 'relationship' => 'Friend'],
        ],
        'submitted_at' => now(),
    ]);

    $page = visit('/applications/'.$application->id);

    usleep(300000);

    $page->script(<<<'JS'
        const btns = Array.from(document.querySelectorAll('button'));
        const schedBtn = btns.find(b => b.textContent.trim() === 'Schedule Interview');
        if (schedBtn) schedBtn.click();
    JS);

    usleep(500000);

    $page->assertSee('Mark interview as scheduled')
        ->assertNoJavaScriptErrors();
});

test('application background check button shows confirm dialog', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin);

    $caregiverUser = User::factory()->create(['role' => 'caregiver', 'email' => 'cg-bg@example.com']);
    $caregiver = Caregiver::create([
        'user_id' => $caregiverUser->id,
        'first_name' => 'Background',
        'last_name' => 'Check',
        'slug' => 'background-check',
        'phone' => '555-111-3333',
        'address_line1' => '333 Birch St',
        'address_city' => 'San Diego',
        'address_state' => 'CA',
        'address_zip' => '92109',
        'date_of_birth' => '1990-01-01',
        'status' => CaregiverStatus::InterviewScheduled->value,
    ]);

    $application = CaregiverApplication::create([
        'caregiver_id' => $caregiver->id,
        'data' => [
            'personal' => ['first_name' => 'Background', 'last_name' => 'Check'],
            'sponsor' => ['first_name' => 'S', 'last_name' => 'P', 'email' => 'sp@example.com', 'phone' => '555', 'relationship' => 'Friend'],
        ],
        'submitted_at' => now(),
    ]);

    $page = visit('/applications/'.$application->id);

    usleep(300000);

    $page->script(<<<'JS'
        const btns = Array.from(document.querySelectorAll('button'));
        const bgBtn = btns.find(b => b.textContent.trim() === 'Start Background Check');
        if (bgBtn) bgBtn.click();
    JS);

    usleep(500000);

    $page->assertSee('Start background check process')
        ->assertNoJavaScriptErrors();
});

test('application hire button shows confirm dialog', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin);

    $caregiverUser = User::factory()->create(['role' => 'caregiver', 'email' => 'cg-hire@example.com']);
    $caregiver = Caregiver::create([
        'user_id' => $caregiverUser->id,
        'first_name' => 'Hire',
        'last_name' => 'Test',
        'slug' => 'hire-test',
        'phone' => '555-222-4444',
        'address_line1' => '444 Ash St',
        'address_city' => 'San Diego',
        'address_state' => 'CA',
        'address_zip' => '92110',
        'date_of_birth' => '1990-01-01',
        'status' => CaregiverStatus::BackgroundCheck->value,
    ]);

    $application = CaregiverApplication::create([
        'caregiver_id' => $caregiver->id,
        'data' => [
            'personal' => ['first_name' => 'Hire', 'last_name' => 'Test'],
            'sponsor' => ['first_name' => 'S', 'last_name' => 'P', 'email' => 'sp@example.com', 'phone' => '555', 'relationship' => 'Friend'],
        ],
        'submitted_at' => now(),
    ]);

    $page = visit('/applications/'.$application->id);

    usleep(300000);

    $page->script(<<<'JS'
        const btns = Array.from(document.querySelectorAll('button'));
        const hireBtn = btns.find(b => b.textContent.trim() === 'Hire');
        if (hireBtn) hireBtn.click();
    JS);

    usleep(500000);

    $page->assertSee('Hire this caregiver')
        ->assertNoJavaScriptErrors();
});

test('application complete onboarding button shows confirm dialog', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin);

    $caregiverUser = User::factory()->create(['role' => 'caregiver', 'email' => 'cg-onboard@example.com']);
    $caregiver = Caregiver::create([
        'user_id' => $caregiverUser->id,
        'first_name' => 'Onboard',
        'last_name' => 'Test',
        'slug' => 'onboard-test',
        'phone' => '555-333-5555',
        'address_line1' => '555 Walnut St',
        'address_city' => 'San Diego',
        'address_state' => 'CA',
        'address_zip' => '92111',
        'date_of_birth' => '1990-01-01',
        'status' => CaregiverStatus::HiredOnboarding->value,
    ]);

    $application = CaregiverApplication::create([
        'caregiver_id' => $caregiver->id,
        'data' => [
            'personal' => ['first_name' => 'Onboard', 'last_name' => 'Test'],
            'sponsor' => ['first_name' => 'S', 'last_name' => 'P', 'email' => 'sp@example.com', 'phone' => '555', 'relationship' => 'Friend'],
        ],
        'submitted_at' => now(),
    ]);

    $page = visit('/applications/'.$application->id);

    usleep(300000);

    $page->script(<<<'JS'
        const btns = Array.from(document.querySelectorAll('button'));
        const onboardBtn = btns.find(b => b.textContent.trim() === 'Complete Onboarding');
        if (onboardBtn) onboardBtn.click();
    JS);

    usleep(500000);

    $page->assertSee('Complete onboarding and activate this caregiver')
        ->assertNoJavaScriptErrors();
});

test('interview evaluation page loads', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin);

    $caregiverUser = User::factory()->create(['role' => 'caregiver', 'email' => 'cg-int-eval@example.com']);
    $caregiver = Caregiver::create([
        'user_id' => $caregiverUser->id,
        'first_name' => 'Eval',
        'last_name' => 'Test',
        'slug' => 'eval-test',
        'phone' => '555-999-0000',
        'address_line1' => '222 Birch St',
        'address_city' => 'San Diego',
        'address_state' => 'CA',
        'address_zip' => '92108',
        'date_of_birth' => '1990-01-01',
        'status' => CaregiverStatus::InterviewScheduled->value,
    ]);

    $application = CaregiverApplication::create([
        'caregiver_id' => $caregiver->id,
        'data' => [
            'personal' => ['first_name' => 'Eval', 'last_name' => 'Test'],
            'sponsor' => ['first_name' => 'S', 'last_name' => 'P', 'email' => 'sp@example.com', 'phone' => '555', 'relationship' => 'Friend'],
        ],
        'submitted_at' => now(),
    ]);

    $page = visit('/applications/'.$application->id.'/interview');

    usleep(500000);

    $page->assertSee('Interview')
        ->assertNoJavaScriptErrors();
});
