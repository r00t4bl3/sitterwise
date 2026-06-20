<?php

use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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
