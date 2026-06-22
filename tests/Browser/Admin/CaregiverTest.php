<?php

use App\Models\Caregiver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin caregiver create page can be viewed', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin);

    visit('/caregivers/create')
        ->assertSee('Add New Caregiver')
        ->assertSee('Personal Information')
        ->assertSee('Account Credentials')
        ->assertSee('Additional Information')
        ->assertSee('Create Caregiver')
        ->assertNoJavaScriptErrors();
});

test('admin can create a caregiver', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin);

    $page = visit('/caregivers/create');

    fillField($page, '#first_name', 'Jane');
    fillField($page, '#last_name', 'Smith');
    fillField($page, '#email', 'jane@example.com');
    fillField($page, 'input[type="tel"]', '5559876543');
    selectOption($page, '#status', 'Active');
    fillField($page, '#password', 'password');
    fillField($page, '#password_confirmation', 'password');
    clickElement($page, 'button[type="submit"]');

    $caregiver = Caregiver::whereHas('user', fn ($q) => $q->where('email', 'jane@example.com'))->first();
    $page->assertPathIs('/caregivers/'.$caregiver->id);
    $page->assertSee('Caregiver Profile');
});

test('admin can view caregiver detail page', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin);

    $user = createCaregiver();
    $caregiver = Caregiver::first();

    session()->put('auth.password_confirmed_at', time());

    visit('/caregivers/'.$caregiver->id)
        ->assertSee('Caregiver Profile')
        ->assertSee('View Jobs')
        ->assertSee('View Availability')
        ->assertSee('View Public Profile')
        ->assertSee('Edit')
        ->assertSee('Reset Password')
        ->assertSee('Status')
        ->assertNoJavaScriptErrors();
});

test('caregivers index loads with table', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin);

    visit('/caregivers')
        ->assertSee('Caregivers')
        ->assertSee('Add Caregiver')
        ->assertNoJavaScriptErrors();
});

test('can search caregivers', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin);

    $user = createCaregiver();
    $caregiver = Caregiver::where('user_id', $user->id)->first();

    $page = visit('/caregivers');

    fillField($page, 'input[placeholder*="Search by name"]', $caregiver->first_name);

    usleep(500000);

    $page->assertNoJavaScriptErrors();
});

test('can filter caregivers by status', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin);

    $caregiver = createCaregiver();

    $page = visit('/caregivers');

    usleep(300000);

    $page->script(<<<'JS'
        const buttons = Array.from(document.querySelectorAll('button'));
        const statusBtn = buttons.find(b => b.textContent.trim() === 'Active');
        if (statusBtn) statusBtn.click();
    JS);

    usleep(500000);

    $page->assertNoJavaScriptErrors();
});

test('admin can edit a caregiver', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin);

    $user = createCaregiver();
    $caregiver = Caregiver::first();

    session()->put('auth.password_confirmed_at', time());

    $page = visit('/caregivers/'.$caregiver->id.'/edit');

    $page->assertSee('Edit Caregiver')
        ->assertNoJavaScriptErrors();
});

test('caregiver profile tabs are navigable', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin);

    $user = createCaregiver();
    $caregiver = Caregiver::first();

    session()->put('auth.password_confirmed_at', time());

    $page = visit('/caregivers/'.$caregiver->id);

    $page->assertSee('Caregiver Profile')
        ->assertSee('Summary')
        ->assertSee('Application')
        ->assertSee('References')
        ->assertSee('Reviews')
        ->assertSee('Internal Rating')
        ->assertSee('Job History')
        ->assertSee('Notes')
        ->assertNoJavaScriptErrors();

    // Click Application tab
    $page->script(<<<'JS'
        const tabs = Array.from(document.querySelectorAll('button'));
        const appTab = tabs.find(b => b.textContent.trim() === 'Application');
        if (appTab) appTab.click();
    JS);
    usleep(300000);

    $page->assertNoJavaScriptErrors();

    // Click Job History tab
    $page->script(<<<'JS'
        const tabs = Array.from(document.querySelectorAll('button'));
        const jobsTab = tabs.find(b => b.textContent.trim() === 'Job History');
        if (jobsTab) jobsTab.click();
    JS);
    usleep(300000);

    $page->assertNoJavaScriptErrors();

    // Click Internal Rating tab
    $page->script(<<<'JS'
        const tabs = Array.from(document.querySelectorAll('button'));
        const ratingTab = tabs.find(b => b.textContent.trim() === 'Internal Rating');
        if (ratingTab) ratingTab.click();
    JS);
    usleep(300000);

    $page->assertNoJavaScriptErrors();

    // Click References tab
    $page->script(<<<'JS'
        const tabs = Array.from(document.querySelectorAll('button'));
        const refTab = tabs.find(b => b.textContent.trim() === 'References');
        if (refTab) refTab.click();
    JS);
    usleep(300000);

    $page->assertNoJavaScriptErrors();
});
