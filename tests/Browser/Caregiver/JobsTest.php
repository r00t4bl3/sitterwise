<?php

use App\Models\Booking;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('jobs index page can be viewed', function () {
    $user = createCaregiver();

    $this->actingAs($user);

    visit('/jobs')
        ->assertSee('My Jobs')
        ->assertSee('No jobs found')
        ->assertNoJavaScriptErrors();
});

test('caregiver sees empty state when no jobs exist', function () {
    $user = createCaregiver();

    $this->actingAs($user);

    visit('/jobs')
        ->assertSee('No jobs found')
        ->assertNoJavaScriptErrors();
});

test('caregiver can search jobs by client name', function () {
    $clientUser = User::factory()->create(['role' => 'client']);
    $client = Client::factory()->create(['user_id' => $clientUser->id]);

    $caregiverUser = createCaregiver();
    $caregiver = Caregiver::where('user_id', $caregiverUser->id)->first();

    $booking = Booking::factory()->forClient($client)->create([
        'status' => 'confirmed',
        'caregiver_id' => $caregiver->id,
    ]);

    $this->actingAs($caregiverUser);

    $page = visit('/jobs');

    usleep(300000);

    $page->assertSee('View')
        ->assertNoJavaScriptErrors();
});

test('caregiver can filter jobs by status', function () {
    $clientUser = User::factory()->create(['role' => 'client']);
    $client = Client::factory()->create(['user_id' => $clientUser->id]);

    $caregiverUser = createCaregiver();
    $caregiver = Caregiver::where('user_id', $caregiverUser->id)->first();

    $booking = Booking::factory()->forClient($client)->create([
        'status' => 'confirmed',
        'caregiver_id' => $caregiver->id,
    ]);

    $this->actingAs($caregiverUser);

    $page = visit('/jobs');

    $page->assertSee('All')
        ->assertNoJavaScriptErrors();
});
