<?php

use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    seedLookupTables();
});

test('transactions index loads with data', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin);
    session()->put('auth.password_confirmed_at', time());

    Booking::factory()->count(3)->create(['status' => 'completed']);

    visit('/transactions')
        ->assertSee('Transactions')
        ->assertSee('Search')
        ->assertNoJavaScriptErrors();
});

test('can search transactions', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin);
    session()->put('auth.password_confirmed_at', time());

    Booking::factory()->count(3)->create(['status' => 'completed']);

    $page = visit('/transactions');

    usleep(300000);

    fillField($page, 'input[type="search"]', '1');

    usleep(300000);

    $page->script(<<<'JS'
        const searchBtn = Array.from(document.querySelectorAll('button'))
            .find(b => b.textContent.trim() === 'Search');
        if (searchBtn) searchBtn.click();
    JS);

    usleep(500000);

    $page->assertNoJavaScriptErrors();
});
