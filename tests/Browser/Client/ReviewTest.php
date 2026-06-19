<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('client can view review page for completed booking', function () {
    [$booking, $client, $caregiver, $clientUser, $caregiverUser] = createCompletedBooking();

    $this->actingAs($clientUser);

    visit("/reviews/{$booking->ulid}")
        ->assertSee('Review')
        ->assertNoJavaScriptErrors();
});
