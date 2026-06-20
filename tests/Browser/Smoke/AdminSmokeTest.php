<?php

use App\Models\Availability;
use App\Models\Booking;
use App\Models\Caregiver;
use App\Models\CaregiverApplication;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    seedLookupTables();
});

test('admin dashboard loads without JavaScript errors', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin);
    session()->put('auth.password_confirmed_at', time());

    visit('/dashboard')
        ->assertSee('Admin Dashboard')
        ->assertNoJavaScriptErrors();
});

test('admin bookings pages load without JavaScript errors', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $clientUser = User::factory()->create(['role' => 'client']);
    $client = Client::factory()->create(['user_id' => $clientUser->id]);
    $booking = Booking::factory()->forClient($client)->create();

    $this->actingAs($admin);
    session()->put('auth.password_confirmed_at', time());

    visit('/bookings')
        ->assertSee('Bookings')
        ->assertNoJavaScriptErrors();

    visit('/bookings/'.$booking->ulid)
        ->assertNoJavaScriptErrors();
});

test('admin client pages load without JavaScript errors', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $clientUser = User::factory()->create(['role' => 'client']);
    $client = Client::factory()->create(['user_id' => $clientUser->id]);

    $this->actingAs($admin);
    session()->put('auth.password_confirmed_at', time());

    visit('/clients')
        ->assertSee('Clients')
        ->assertNoJavaScriptErrors();

    visit('/clients/create')
        ->assertNoJavaScriptErrors();

    visit('/clients/'.$client->id)
        ->assertNoJavaScriptErrors();

    visit('/clients/'.$client->id.'/edit')
        ->assertNoJavaScriptErrors();

    visit('/clients/'.$client->id.'/bookings')
        ->assertNoJavaScriptErrors();
});

test('admin caregiver pages load without JavaScript errors', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $caregiverUser = User::factory()->create(['role' => 'caregiver']);
    $caregiver = Caregiver::factory()->create(['user_id' => $caregiverUser->id]);

    $this->actingAs($admin);
    session()->put('auth.password_confirmed_at', time());

    visit('/caregivers')
        ->assertSee('Caregivers')
        ->assertNoJavaScriptErrors();

    visit('/caregivers/create')
        ->assertNoJavaScriptErrors();

    visit('/caregivers/'.$caregiver->id)
        ->assertNoJavaScriptErrors();

    visit('/caregivers/'.$caregiver->id.'/edit')
        ->assertNoJavaScriptErrors();

    visit('/caregivers/'.$caregiver->id.'/jobs')
        ->assertNoJavaScriptErrors();
});

test('admin application pages load without JavaScript errors', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $caregiverUser = User::factory()->create(['role' => 'caregiver']);
    $caregiver = Caregiver::factory()->create(['user_id' => $caregiverUser->id]);
    $application = CaregiverApplication::create([
        'caregiver_id' => $caregiver->id,
        'data' => ['sponsor' => ['first_name' => 'Test', 'last_name' => 'User', 'email' => 'test@example.com', 'phone' => '555-0000', 'relationship' => 'Friend']],
        'submitted_at' => now(),
    ]);

    $this->actingAs($admin);
    session()->put('auth.password_confirmed_at', time());

    visit('/applications')
        ->assertNoJavaScriptErrors();

    visit('/applications/'.$application->id)
        ->assertNoJavaScriptErrors();

    visit('/applications/'.$application->id.'/interview')
        ->assertNoJavaScriptErrors();
});

test('admin transactions page loads without JavaScript errors', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin);
    session()->put('auth.password_confirmed_at', time());

    visit('/transactions')
        ->assertNoJavaScriptErrors();
});

test('admin availabilities pages load without JavaScript errors', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $availability = Availability::factory()->create();

    $this->actingAs($admin);
    session()->put('auth.password_confirmed_at', time());

    visit('/availabilities')
        ->assertNoJavaScriptErrors();

    visit('/availabilities/'.$availability->id)
        ->assertNoJavaScriptErrors();
});

test('admin settings pages load without JavaScript errors', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin);
    session()->put('auth.password_confirmed_at', time());

    visit('/settings/profile')
        ->assertSee('Profile information')
        ->assertNoJavaScriptErrors();

    visit('/settings/security')
        ->assertNoJavaScriptErrors();

    visit('/settings/appearance')
        ->assertSee('Appearance settings')
        ->assertNoJavaScriptErrors();

    visit('/settings/push-notifications')
        ->assertNoJavaScriptErrors();
});
