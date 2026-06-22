<?php

use App\Models\Booking;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    seedLookupTables();
});

test('bookings index loads in calendar view', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin);

    visit('/bookings')
        ->assertSee('Bookings')
        ->assertNoJavaScriptErrors();
});

test('can switch to table view', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin);

    Booking::factory()->count(3)->create();

    $page = visit('/bookings');

    usleep(300000);

    $page->script(<<<'JS'
        const btn = document.querySelector('button[title="Table View"]');
        if (btn) btn.click();
    JS);

    usleep(300000);

    $page->assertNoJavaScriptErrors();
});

test('can search bookings', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin);

    $booking = Booking::factory()->create([
        'start_datetime' => now()->startOfMonth()->addDays(5)->setHour(9)->setMinute(0),
        'end_datetime' => now()->startOfMonth()->addDays(5)->setHour(13)->setMinute(0),
    ]);

    $page = visit('/bookings');

    usleep(300000);

    fillField($page, 'input[placeholder*="Search by client"]', $booking->bookingGroup->client_first_name);

    usleep(500000);

    $page->assertNoJavaScriptErrors();
});

test('can navigate between months', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin);

    Booking::factory()->count(2)->create();

    $page = visit('/bookings');

    usleep(300000);

    $page->script(<<<'JS'
        const btn = document.querySelector('button[title="Next month"]');
        if (btn) btn.click();
    JS);

    usleep(500000);

    $page->assertNoJavaScriptErrors();
});

test('can filter bookings by status', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin);

    Booking::factory()->count(3)->create(['status' => 'received']);

    $page = visit('/bookings');

    usleep(300000);

    $page->script(<<<'JS'
        const buttons = Array.from(document.querySelectorAll('button'));
        const statusBtn = buttons.find(b =>
            b.textContent.trim() === 'Received' &&
            b.closest('.flex')?.querySelector('input[type="text"]')
        );
        if (statusBtn) statusBtn.click();
    JS);

    usleep(300000);

    $page->assertNoJavaScriptErrors();
});

test('booking sheet opens in create mode', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin);

    $page = visit('/bookings');
    usleep(500000);

    $page->script(<<<'JS'
        const btn = Array.from(document.querySelectorAll('button'))
            .find(b => b.textContent.trim() === 'Create Booking');
        if (btn) btn.click();
    JS);

    usleep(800000);

    $page->assertSee('Create Booking')
        ->assertSee('Fill in the details to create a new booking.')
        ->assertNoJavaScriptErrors();
});

test('booking sheet shows form fields in create mode', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin);

    $clientUser = createClientUser();

    $page = visit('/bookings');
    usleep(500000);

    $page->script(<<<'JS'
        const btn = Array.from(document.querySelectorAll('button'))
            .find(b => b.textContent.trim() === 'Create Booking');
        if (btn) btn.click();
    JS);

    usleep(800000);

    $page->assertSee('Create Booking')
        ->assertSee('Personal Info')
        ->assertSee('Booking Details')
        ->assertNoJavaScriptErrors();
});

test('booking sheet opens in duplicate mode', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin);

    $clientUser = createClientUser();
    $booking = Booking::factory()->forClient(Client::first())->create([
        'start_datetime' => now()->addDays(10)->setHour(9)->setMinute(0),
        'end_datetime' => now()->addDays(10)->setHour(13)->setMinute(0),
    ]);

    $page = visit('/bookings');
    usleep(500000);

    // Switch to table view
    $page->script(<<<'JS'
        const btn = document.querySelector('button[title="Table View"]');
        if (btn) btn.click();
    JS);
    usleep(500000);

    // Click Duplicate button
    $page->script(<<<'JS'
        const dupBtn = Array.from(document.querySelectorAll('button'))
            .find(b => b.textContent.trim() === 'Duplicate');
        if (dupBtn) dupBtn.click();
    JS);
    usleep(800000);

    $page->assertNoJavaScriptErrors();
});

test('booking sheet opens for existing booking', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin);

    $clientUser = createClientUser();
    $booking = Booking::factory()->forClient(Client::first())->create([
        'start_datetime' => now()->addDays(10)->setHour(9)->setMinute(0),
        'end_datetime' => now()->addDays(10)->setHour(13)->setMinute(0),
    ]);

    $page = visit('/bookings');
    usleep(500000);

    // Click on a booking card in the calendar to open edit sheet
    $page->script(<<<'JS'
        const bookingBtns = document.querySelectorAll(
            'button.flex.w-full.cursor-pointer.items-start.gap-2.rounded-\\[3px\\]'
        );
        if (bookingBtns.length > 0) bookingBtns[0].click();
    JS);
    usleep(1000000);

    $page->assertNoJavaScriptErrors();
});

test('booking show page loads', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin);

    $clientUser = createClientUser();
    $booking = Booking::factory()->forClient(Client::first())->create();

    $booking->load('bookingGroup.bookings', 'caregiver.user', 'client');

    visit('/bookings/'.$booking->ulid)
        ->assertSee('Back to Bookings')
        ->assertNoJavaScriptErrors();
});

test('admin can cancel a booking from show page', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin);

    $clientUser = createClientUser();
    $booking = Booking::factory()->forClient(Client::first())->confirmed()->create();

    $page = visit('/bookings/'.$booking->ulid);
    usleep(500000);

    $page->assertSee('Cancel Booking');

    // Click Cancel Booking to open the dialog
    $page->script(<<<'JS'
        const btn = Array.from(document.querySelectorAll('button'))
            .find(b => b.textContent.trim() === 'Cancel Booking');
        if (btn) btn.click();
    JS);
    usleep(500000);

    $page->assertSee('Cancellation Reason');

    fillTextarea($page, 'Explain why', 'Test cancellation from browser test');
    usleep(200000);

    // Submit cancel form — find the submit button inside the dialog
    $page->script(<<<'JS'
        const buttons = Array.from(document.querySelectorAll('button'));
        const submit = buttons.find(b =>
            b.textContent.trim() === 'Cancel Booking' &&
            b.closest('[role="dialog"]')
        );
        if (submit) submit.click();
    JS);
    usleep(1000000);

    $page->assertNoJavaScriptErrors();
});

test('admin can open replace caregiver sheet', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin);

    $clientUser = createClientUser();
    $booking = Booking::factory()->forClient(Client::first())->confirmed()->create();

    $page = visit('/bookings/'.$booking->ulid);
    usleep(500000);

    // Click Replace button next to caregiver name
    $page->script(<<<'JS'
        const btn = Array.from(document.querySelectorAll('button'))
            .find(b => b.textContent.trim() === 'Replace');
        if (btn) btn.click();
    JS);
    usleep(500000);

    $page->assertNoJavaScriptErrors();
});

test('admin can open notify caregivers sheet', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin);

    $clientUser = createClientUser();
    $booking = Booking::factory()->forClient(Client::first())->create();

    $page = visit('/bookings/'.$booking->ulid);
    usleep(500000);

    // Notify Caregivers button shows when status is received or pending (default is received)
    $page->script(<<<'JS'
        const btn = Array.from(document.querySelectorAll('button'))
            .find(b => b.textContent.trim() === 'Notify Caregivers');
        if (btn) btn.click();
    JS);
    usleep(500000);

    $page->assertNoJavaScriptErrors();
});

test('admin can open delete booking dialog', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin);

    $clientUser = createClientUser();
    $booking = Booking::factory()->forClient(Client::first())->create();

    $page = visit('/bookings/'.$booking->ulid);
    usleep(500000);

    // Click Delete Booking button
    $page->script(<<<'JS'
        const btn = Array.from(document.querySelectorAll('button'))
            .find(b => b.textContent.trim() === 'Delete Booking');
        if (btn) btn.click();
    JS);
    usleep(500000);

    $page->assertSee('Delete Booking')
        ->assertNoJavaScriptErrors();
});
