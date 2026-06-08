<?php

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('bookings index page can be viewed', function () {
    $user = User::factory()->create();

    Client::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    visit('/bookings')
        ->assertSee('Bookings')
        ->assertSee('No bookings found')
        ->assertNoJavaScriptErrors();
});

test('bookings index page has create booking link', function () {
    $user = User::factory()->create();

    Client::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    $page = visit('/bookings');

    $page->assertSee('Create Booking');

    $page->script(<<<'JS'
        document.querySelector('a[href="/bookings/create"]').click();
    JS);

    $page->assertPathIs('/bookings/create');
});
