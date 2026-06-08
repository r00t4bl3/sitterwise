<?php

use App\Models\Caregiver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin caregiver create page can be viewed', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin);

    visit('/caregivers/create')
        ->assertSee('Add New Caregiver')
        ->assertSee('Personal Information')
        ->assertSee('Account Credentials')
        ->assertSee('Additional Information')
        ->assertSee('Create Caregiver')
        ->assertNoJavaScriptErrors();
});

test('admin can create a caregiver', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin);

    $page = visit('/caregivers/create');

    fillField($page, '#first_name', 'Jane');
    fillField($page, '#last_name', 'Smith');
    fillField($page, '#email', 'jane@example.com');
    fillField($page, 'input[type="tel"]', '5559876543');
    selectOption($page, '#status', 'Active');
    fillField($page, '#password', 'password');
    fillField($page, '#password_confirmation', 'password');
    clickElement($page, 'button[type="submit"]');

    $caregiver = Caregiver::whereHas('user', fn ($q) => $q->where('email', 'jane@example.com'))->first();
    $page->assertPathIs('/caregivers/'.$caregiver->id);
    $page->assertSee('Caregiver Profile');
});

test('admin can view caregiver detail page', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin);

    $user = createCaregiver();
    $caregiver = Caregiver::first();

    session()->put('auth.password_confirmed_at', time());

    visit('/caregivers/'.$caregiver->id)
        ->assertSee('Caregiver Profile')
        ->assertSee('View Jobs')
        ->assertSee('View Availability')
        ->assertSee('View Public Profile')
        ->assertSee('Edit')
        ->assertSee('Reset Password')
        ->assertSee('Status')
        ->assertNoJavaScriptErrors();
});
