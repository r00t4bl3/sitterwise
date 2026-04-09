<?php

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin']);
    $this->client = Client::factory()->hasAddresses()->create();
});

describe('ClientController', function () {
    // Guest tests
    test('guests are redirected to login when accessing clients index', function () {
        $response = $this->get(route('clients.index'));
        $response->assertRedirect(route('login'));
    });

    test('guests are redirected to login when accessing client show page', function () {
        $response = $this->get(route('clients.show', $this->client));
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
        $response = $this->get(route('clients.getClientData', $this->client));
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

        $response = $this->get(route('clients.show', $this->client));
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

        $response = $this->get(route('clients.getClientData', $this->client));
        $response->assertForbidden();
    });

    // Admin user tests
    test('admin users can view clients index', function () {
        $this->actingAs($this->user);

        $response = $this->get(route('clients.index'));
        $response->assertSuccessful();
    });

    test('admin users can view client show page', function () {
        $this->actingAs($this->user);

        $response = $this->get(route('clients.show', $this->client));
        $response->assertSuccessful();
    });

    test('admin users can view client create page', function () {
        $this->actingAs($this->user);

        $response = $this->get(route('clients.create'));
        $response->assertSuccessful();
    });

    test('admin users can view client edit page', function () {
        $this->actingAs($this->user);

        $response = $this->get(route('clients.edit', $this->client));
        $response->assertSuccessful();
    });

    test('admin users can create a client', function () {
        $this->actingAs($this->user);

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
        $this->actingAs($this->user);

        $response = $this->patch(route('clients.update', $this->client), [
            'first_name' => 'UpdatedFirstName',
            'last_name' => $this->client->last_name,
            'phone' => $this->client->phone,
            'client_type' => $this->client->client_type,
        ]);

        $response->assertRedirect();

        $this->client->refresh();
        expect($this->client->first_name)->toBe('UpdatedFirstName');
    });

    test('admin users can search clients', function () {
        $this->actingAs($this->user);

        Client::factory()->create([
            'first_name' => 'SearchableName',
            'last_name' => 'Test',
        ]);

        $response = $this->get(route('clients.index', ['search' => 'Searchable']));
        $response->assertSuccessful();
    });

    test('admin users can access search suggestions endpoint', function () {
        $this->actingAs($this->user);

        Client::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $response = $this->get(route('clients.searchSuggestions', ['q' => 'John']));
        $response->assertSuccessful();
        $response->assertHeader('content-type', 'application/json');
    });

    test('admin users can access client data endpoint', function () {
        $this->actingAs($this->user);

        $response = $this->get(route('clients.getClientData', $this->client));
        $response->assertSuccessful();
        $response->assertHeader('content-type', 'application/json');
    });

    test('client index shows client list with pagination', function () {
        $this->actingAs($this->user);

        Client::factory()->count(5)->create();

        $response = $this->get(route('clients.index'));
        $response->assertSuccessful();
    });

    test('client show page displays all client information', function () {
        $this->actingAs($this->user);

        $response = $this->get(route('clients.show', $this->client));
        $response->assertSuccessful();

        $response->assertSee($this->client->first_name);
        $response->assertSee($this->client->last_name);
    });

    test('admin users can reset client password', function () {
        $this->actingAs($this->user);

        $response = $this->post(route('clients.resetPassword', $this->client), [
            'new_password' => 'newpassword123',
            'new_password_confirmation' => 'newpassword123',
        ]);

        $response->assertRedirect();

        $this->client->refresh();
        expect(Hash::check('newpassword123', $this->client->user->password))->toBeTrue();
    });

    test('admin users cannot reset client password with mismatched confirmation', function () {
        $this->actingAs($this->user);

        $oldPassword = $this->client->user->password;

        $response = $this->post(route('clients.resetPassword', $this->client), [
            'new_password' => 'newpassword123',
            'new_password_confirmation' => 'differentpassword123',
        ]);

        $response->assertSessionHasErrors();

        $this->client->refresh();
        expect($this->client->user->password)->toBe($oldPassword);
    });
});
