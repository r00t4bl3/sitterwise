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

test('super admin dashboard loads without JavaScript errors', function () {
    $admin = User::factory()->create(['role' => 'super_admin']);

    $this->actingAs($admin);
    session()->put('auth.password_confirmed_at', time());

    visit('/dashboard')
        ->assertSee('SuperAdmin Dashboard')
        ->assertNoJavaScriptErrors();
});

test('super admin bookings pages load without JavaScript errors', function () {
    $admin = User::factory()->create(['role' => 'super_admin']);
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

test('super admin client pages load without JavaScript errors', function () {
    $admin = User::factory()->create(['role' => 'super_admin']);
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

test('super admin caregiver pages load without JavaScript errors', function () {
    $admin = User::factory()->create(['role' => 'super_admin']);
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

test('super admin application pages load without JavaScript errors', function () {
    $admin = User::factory()->create(['role' => 'super_admin']);
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

test('super admin transactions page loads without JavaScript errors', function () {
    $admin = User::factory()->create(['role' => 'super_admin']);

    $this->actingAs($admin);
    session()->put('auth.password_confirmed_at', time());

    visit('/transactions')
        ->assertNoJavaScriptErrors();
});

test('super admin availabilities pages load without JavaScript errors', function () {
    $admin = User::factory()->create(['role' => 'super_admin']);
    $availability = Availability::factory()->create();

    $this->actingAs($admin);
    session()->put('auth.password_confirmed_at', time());

    visit('/availabilities')
        ->assertNoJavaScriptErrors();

    visit('/availabilities/'.$availability->id)
        ->assertNoJavaScriptErrors();
});

test('super admin settings pages load without JavaScript errors', function () {
    $admin = User::factory()->create(['role' => 'super_admin']);

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

test('super admin system pages load without JavaScript errors', function () {
    $admin = User::factory()->create(['role' => 'super_admin']);

    $this->actingAs($admin);
    session()->put('auth.password_confirmed_at', time());

    visit('/talking-points')
        ->assertNoJavaScriptErrors();

    visit('/broadcast-sms')
        ->assertNoJavaScriptErrors();

    visit('/locations')
        ->assertNoJavaScriptErrors();

    visit('/hotels')
        ->assertNoJavaScriptErrors();

    visit('/attributes')
        ->assertNoJavaScriptErrors();

    visit('/specialties')
        ->assertNoJavaScriptErrors();

    visit('/certifications')
        ->assertNoJavaScriptErrors();

    visit('/pricing-rules')
        ->assertNoJavaScriptErrors();

    visit('/quick-links')
        ->assertNoJavaScriptErrors();
});
