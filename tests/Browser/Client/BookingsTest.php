<?php

use App\Models\Booking;
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

test('bookings index shows client bookings', function () {
    $user = createClientUser();
    $client = Client::first();

    $booking = Booking::factory()->forClient($client)->create();

    $this->actingAs($user);

    visit('/bookings')
        ->assertSee('View Details')
        ->assertNoJavaScriptErrors();
});

test('bookings index shows booking status', function () {
    $user = createClientUser();
    $client = Client::first();

    $booking = Booking::factory()->forClient($client)->create([
        'status' => 'confirmed',
    ]);

    $this->actingAs($user);

    visit('/bookings')
        ->assertNoJavaScriptErrors();
});

test('client sees empty state when no bookings exist', function () {
    $user = createClientUser();

    $this->actingAs($user);

    visit('/bookings')
        ->assertSee('No bookings found')
        ->assertNoJavaScriptErrors();
});
