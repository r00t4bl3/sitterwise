<?php

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

// Guest tests
test('guests are redirected to login when accessing clients index', function () {
    $response = $this->get(route('clients.index'));
    $response->assertRedirect(route('login'));
});

test('guests are redirected to login when accessing client show page', function () {
    $client = Client::factory()->create();
    $response = $this->get(route('clients.show', $client));
    $response->assertRedirect(route('login'));
});

test('guests cannot store a new client', function () {
    $response = $this->post(route('clients.store'), [
        'first_name' => 'Test',
        'last_name' => 'Client',
        'email' => 'test@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'phone' => '1234567890',
        'client_type' => 'vacationer',
    ]);

    $response->assertRedirect(route('login'));
});

test('guests cannot access search suggestions', function () {
    $response = $this->get(route('clients.searchSuggestions', ['q' => 'John']));
    $response->assertRedirect(route('login'));
});

test('guests cannot access client data endpoint', function () {
    $client = Client::factory()->create();
    $response = $this->get(route('clients.getClientData', $client));
    $response->assertRedirect(route('login'));
});

// Regular authenticated user (non-admin) tests
test('regular users cannot view clients index', function () {
    $user = User::factory()->create(['role' => 'caregiver']);
    $this->actingAs($user);

    $response = $this->get(route('clients.index'));
    $response->assertForbidden();
});

test('regular users cannot view client show page', function () {
    $user = User::factory()->create(['role' => 'caregiver']);
    $this->actingAs($user);

    $client = Client::factory()->create();
    $response = $this->get(route('clients.show', $client));
    $response->assertForbidden();
});

test('regular users cannot access search suggestions - forbidden', function () {
    $user = User::factory()->create(['role' => 'caregiver']);
    $this->actingAs($user);

    $response = $this->get(route('clients.searchSuggestions', ['q' => 'John']));
    $response->assertForbidden();
});

test('regular users cannot access client data endpoint - forbidden', function () {
    $user = User::factory()->create(['role' => 'caregiver']);
    $this->actingAs($user);

    $client = Client::factory()->create();
    $response = $this->get(route('clients.getClientData', $client));
    $response->assertForbidden();
});

// Admin user tests
test('admin users can view clients index', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $this->actingAs($user);

    $response = $this->get(route('clients.index'));
    $response->assertSuccessful();
});

test('admin users can view client show page', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $this->actingAs($user);

    $client = Client::factory()->create();
    $response = $this->get(route('clients.show', $client));
    $response->assertSuccessful();
});

test('admin users can view client create page', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $this->actingAs($user);

    $response = $this->get(route('clients.create'));
    $response->assertSuccessful();
});

test('admin users can view client edit page', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $this->actingAs($user);

    $client = Client::factory()->create();
    $response = $this->get(route('clients.edit', $client));
    $response->assertSuccessful();
});

test('admin users can create a client', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $this->actingAs($user);

    $response = $this->post(route('clients.store'), [
        'first_name' => 'Test',
        'last_name' => 'Client',
        'email' => 'newclient@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'phone' => '1234567890',
        'client_type' => 'vacationer',
    ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('users', [
        'email' => 'newclient@example.com',
    ]);
});

test('admin users can update a client', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $this->actingAs($user);

    $client = Client::factory()->create();

    $response = $this->patch(route('clients.update', $client), [
        'first_name' => 'UpdatedFirstName',
        'last_name' => $client->last_name,
        'phone' => $client->phone,
        'client_type' => $client->client_type,
    ]);

    $response->assertRedirect();

    $client->refresh();
    expect($client->first_name)->toBe('UpdatedFirstName');
});

test('admin users can search clients', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $this->actingAs($user);

    $client = Client::factory()->create([
        'first_name' => 'SearchableName',
        'last_name' => 'Test',
    ]);

    $response = $this->get(route('clients.index', ['search' => 'Searchable']));
    $response->assertSuccessful();
});

test('admin users can access search suggestions endpoint', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $this->actingAs($user);

    $client = Client::factory()->create([
        'first_name' => 'John',
        'last_name' => 'Doe',
    ]);

    $response = $this->get(route('clients.searchSuggestions', ['q' => 'John']));
    $response->assertSuccessful();
    $response->assertHeader('content-type', 'application/json');
});

test('admin users can access client data endpoint', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $this->actingAs($user);

    $client = Client::factory()->create();

    $response = $this->get(route('clients.getClientData', $client));
    $response->assertSuccessful();
    $response->assertHeader('content-type', 'application/json');
});

test('client index shows client list with pagination', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $this->actingAs($user);

    Client::factory()->count(5)->create();

    $response = $this->get(route('clients.index'));
    $response->assertSuccessful();
});

test('client show page displays all client information', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $this->actingAs($user);

    $client = Client::factory()->create();

    $response = $this->get(route('clients.show', $client));
    $response->assertSuccessful();

    $response->assertSee($client->first_name);
    $response->assertSee($client->last_name);
});

test('guests cannot reset client password', function () {
    $client = Client::factory()->create();

    $response = $this->post(route('clients.resetPassword', $client), [
        'new_password' => 'newpassword123',
        'new_password_confirmation' => 'newpassword123',
    ]);

    $response->assertRedirect(route('login'));
});

test('regular users cannot reset client password - forbidden', function () {
    $user = User::factory()->create(['role' => 'caregiver']);
    $this->actingAs($user);

    $client = Client::factory()->create();

    $response = $this->post(route('clients.resetPassword', $client), [
        'new_password' => 'newpassword123',
        'new_password_confirmation' => 'newpassword123',
    ]);

    $response->assertForbidden();
});

test('admin users can reset client password', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $this->actingAs($user);

    $client = Client::factory()->create();

    $response = $this->post(route('clients.resetPassword', $client), [
        'new_password' => 'newpassword123',
        'new_password_confirmation' => 'newpassword123',
    ]);

    $response->assertRedirect();

    $client->refresh();
    expect(Hash::check('newpassword123', $client->user->password))->toBeTrue();
});

test('admin users cannot reset client password with mismatched confirmation', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $this->actingAs($user);

    $client = Client::factory()->create();
    $oldPassword = $client->user->password;

    $response = $this->post(route('clients.resetPassword', $client), [
        'new_password' => 'newpassword123',
        'new_password_confirmation' => 'differentpassword123',
    ]);

    $response->assertSessionHasErrors();

    $client->refresh();
    expect($client->user->password)->toBe($oldPassword);
});
