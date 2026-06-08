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
