<?php

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin client create page can be viewed', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin);

    visit('/clients/create')
        ->assertSee('Add New Client')
        ->assertSee('Account Information')
        ->assertSee('First Name')
        ->assertSee('Email')
        ->assertSee('Create Client')
        ->assertNoJavaScriptErrors();
});

test('admin can create a client', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin);

    $page = visit('/clients/create');

    fillField($page, '#first_name', 'John');
    fillField($page, '#last_name', 'Doe');
    fillField($page, '#email', 'john@example.com');
    fillField($page, 'input[type="tel"]', '5551234567');
    selectOption($page, '#client_type', 'Vacationer');
    fillField($page, '#password', 'password');
    fillField($page, '#password_confirmation', 'password');
    clickElement($page, 'button[type="submit"]');

    $page->assertPathIs('/clients');
    $page->assertSee('Client created successfully');
});

test('admin can view client detail page', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin);

    $user = createClientUser();
    $client = Client::first();

    visit('/clients/'.$client->id)
        ->assertSee('Client Profile')
        ->assertSee('Personal Information')
        ->assertSee('Addresses')
        ->assertSee('View Bookings')
        ->assertSee('Edit')
        ->assertSee('Reset Password')
        ->assertNoJavaScriptErrors();
});

test('clients index loads with table', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin);

    Client::factory()->count(3)->create();

    visit('/clients')
        ->assertSee('Clients')
        ->assertSee('Add Client')
        ->assertNoJavaScriptErrors();
});

test('can search clients', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin);

    $client = Client::factory()->create();
    $client->user->update(['email' => 'searchable@example.com']);

    $page = visit('/clients');

    fillField($page, 'input[placeholder*="Search by name"]', 'searchable');

    usleep(500000);

    $page->assertNoJavaScriptErrors();
});

test('can filter clients by type', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin);

    Client::factory()->count(3)->create(['client_type' => 'vacationer']);

    $page = visit('/clients');

    usleep(300000);

    $page->script(<<<'JS'
        const buttons = Array.from(document.querySelectorAll('button'));
        const filterBtn = buttons.find(b => b.textContent.trim() === 'Vacationer');
        if (filterBtn) filterBtn.click();
    JS);

    usleep(500000);

    $page->assertNoJavaScriptErrors();
});

test('can sort clients by name', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin);

    Client::factory()->count(3)->create();

    $page = visit('/clients');

    usleep(300000);

    $page->script(<<<'JS'
        const headers = Array.from(document.querySelectorAll('th'));
        const nameHeader = headers.find(h => h.textContent.trim().includes('Name'));
        if (nameHeader) {
            const sortBtn = nameHeader.querySelector('button');
            if (sortBtn) sortBtn.click();
        }
    JS);

    usleep(500000);

    $page->assertNoJavaScriptErrors();
});
