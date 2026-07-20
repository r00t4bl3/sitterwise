<?php

use App\Models\Caregiver;
use App\Models\Client;
use App\Models\ClientPaymentMethod;
use App\Models\User;
use Database\Seeders\AttributeDefinitionSeeder;
use Database\Seeders\CertificationTypeSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\SpecialtyTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $this->seed([
        CertificationTypeSeeder::class,
        SpecialtyTypeSeeder::class,
        LocationSeeder::class,
        AttributeDefinitionSeeder::class,
    ]);
    $this->user = User::factory()->create(['role' => 'admin']);
    $this->client = Client::factory()->create();
});

describe('Client - Admin', function () {
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
            'phone' => '+11234567890',
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

    test('the index renders a client whose user record is missing', function () {
        $this->actingAs($this->user);

        // Reproduce the orphan: a client referencing a user that no longer exists.
        $client = Client::factory()->create();
        $client->user()->delete();

        $this->get(route('clients.index', ['sort' => 'id', 'direction' => 'desc']))
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->where('clients.data.0.id', $client->id)
                ->where('clients.data.0.user', null)
            );
    });

    test('the show page renders a client whose user record is missing', function () {
        $this->actingAs($this->user);

        $client = Client::factory()->create();
        $client->user()->delete();

        $this->get(route('clients.show', $client))->assertSuccessful();
    });

    test('the show page renders when an assigned caregiver has no user record', function () {
        $this->actingAs($this->user);

        $caregiver = Caregiver::factory()->create();
        $caregiver->user()->delete();
        $this->client->favoriteCaregivers()->attach($caregiver->id);

        $this->get(route('clients.show', $this->client))->assertSuccessful();
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
            'biography' => 'Just a common client',
            'email' => 'newclient@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone' => '+11234567890',
            'client_type' => 'vacationer',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'newclient@example.com',
        ]);

        $this->assertDatabaseHas('clients', [
            'first_name' => 'Test',
            'last_name' => 'Client',
            'biography' => 'Just a common client',
            'phone' => '+11234567890',
            'client_type' => 'vacationer',
        ]);
    });

    test('admin users can update a client', function () {
        $this->actingAs($this->user);
        $newBiography = 'Updated biography';

        $response = $this->patch(route('clients.update', $this->client), [
            'first_name' => 'UpdatedFirstName',
            'last_name' => $this->client->last_name,
            'phone' => $this->client->phone,
            'client_type' => $this->client->client_type,
            'biography' => $newBiography,
        ]);

        $response->assertRedirect();
        $this->client->refresh();
        expect($this->client->first_name)->toBe('UpdatedFirstName');
        expect($this->client->biography)->toBe($newBiography);
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

    test('admin users can search clients by first name', function () {
        $this->actingAs($this->user);

        Client::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);
        Client::factory()->create([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
        ]);
        Client::factory()->create([
            'first_name' => 'Alice',
            'last_name' => 'Jones',
        ]);

        $response = $this->get(route('clients.searchSuggestions', ['q' => 'John']));
        $response->assertSuccessful();
        $response->assertHeader('content-type', 'application/json');
        $names = collect($response->json())->pluck('name');
        expect($names)->toContain('John Doe')
            ->and($names)->not->toContain('Jane Smith')
            ->and($names)->not->toContain('Alice Jones');
    });

    test('admin users can search clients by email', function () {
        $this->actingAs($this->user);

        $client = Client::factory()->create([
            'first_name' => 'Sarah',
            'last_name' => 'Connor',
        ]);
        $client->user->update(['email' => 'sarah.connor@example.com']);

        Client::factory()->create([
            'first_name' => 'Alice',
            'last_name' => 'Jones',
        ]);

        $response = $this->get(route('clients.searchSuggestions', ['q' => 'sarah.connor']));
        $response->assertSuccessful();
        $response->assertHeader('content-type', 'application/json');
        $results = $response->json();
        expect($results)->toHaveCount(1)
            ->and($results[0]['name'])->toBe('Sarah Connor');
    });

    test('search suggestions returns empty when no match', function () {
        $this->actingAs($this->user);

        Client::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $response = $this->get(route('clients.searchSuggestions', ['q' => 'ZZZZZZ']));
        $response->assertSuccessful();
        expect($response->json())->toBeEmpty();
    });

    test('admin users can search clients by full name', function () {
        $this->actingAs($this->user);

        Client::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);
        Client::factory()->create([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
        ]);

        $response = $this->get(route('clients.searchSuggestions', ['q' => 'John Doe']));
        $response->assertSuccessful();
        $names = collect($response->json())->pluck('name');
        expect($names)->toContain('John Doe')
            ->and($names)->not->toContain('Jane Doe');
    });

    test('admin users can access client data endpoint', function () {
        $this->actingAs($this->user);

        $response = $this->get(route('clients.getClientData', $this->client));
        $response->assertSuccessful();
        $response->assertHeader('content-type', 'application/json');
    });

    test('a client is chargeable only with both a Stripe customer and an active card', function () {
        $ready = Client::factory()->create(['stripe_customer_id' => 'cus_ready']);
        ClientPaymentMethod::factory()->create([
            'client_id' => $ready->id,
            'status' => 'active',
        ]);
        expect($ready->fresh()->hasPaymentCapability())->toBeTrue();

        // Active card but no Stripe customer -> has a card, but not chargeable.
        $noCustomer = Client::factory()->create(['stripe_customer_id' => null]);
        ClientPaymentMethod::factory()->create([
            'client_id' => $noCustomer->id,
            'status' => 'active',
        ]);
        expect($noCustomer->fresh()->hasPaymentMethod())->toBeTrue();
        expect($noCustomer->fresh()->hasPaymentCapability())->toBeFalse();

        // Stripe customer but no active card -> not chargeable.
        $noCard = Client::factory()->create(['stripe_customer_id' => 'cus_nocard']);
        expect($noCard->fresh()->hasPaymentCapability())->toBeFalse();
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

    test('admin can see favorite and blocked caregivers on edit page', function () {
        $this->actingAs($this->user);
        $caregiver1 = Caregiver::factory()->create();
        $caregiver2 = Caregiver::factory()->create();
        $this->client->favoriteCaregivers()->attach($caregiver1->id);
        $this->client->blockedCaregivers()->attach($caregiver2->id);

        $response = $this->get(route('clients.edit', $this->client));

        $response->assertSuccessful();
        $response->assertInertia(fn ($page) => $page
            ->has('favorite_caregivers')
            ->has('blocked_caregivers')
            ->has('caregivers')
        );
    });

    test('admin can update favorite caregivers', function () {
        $this->actingAs($this->user);
        $caregivers = Caregiver::factory()->count(3)->create();
        $ids = $caregivers->pluck('id')->toArray();

        $response = $this->patch(route('clients.update', $this->client), [
            'first_name' => $this->client->first_name,
            'last_name' => $this->client->last_name,
            'phone' => $this->client->phone,
            'client_type' => $this->client->client_type,
            'favorite_caregiver_ids' => $ids,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('client_favorite_caregivers', [
            'client_id' => $this->client->id,
            'caregiver_id' => $ids[0],
        ]);
    });

    test('admin can update blocked caregivers', function () {
        $this->actingAs($this->user);
        $caregivers = Caregiver::factory()->count(2)->create();
        $ids = $caregivers->pluck('id')->toArray();

        $response = $this->patch(route('clients.update', $this->client), [
            'first_name' => $this->client->first_name,
            'last_name' => $this->client->last_name,
            'phone' => $this->client->phone,
            'client_type' => $this->client->client_type,
            'blocked_caregiver_ids' => $ids,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('client_blocked_caregivers', [
            'client_id' => $this->client->id,
            'caregiver_id' => $ids[0],
        ]);
    });

    test('validation fails for invalid caregiver ids', function () {
        $this->actingAs($this->user);

        $response = $this->patch(route('clients.update', $this->client), [
            'first_name' => $this->client->first_name,
            'last_name' => $this->client->last_name,
            'phone' => $this->client->phone,
            'client_type' => $this->client->client_type,
            'favorite_caregiver_ids' => [999],
        ]);

        $response->assertSessionHasErrors();
    });

    test('admin can remove favorite caregivers', function () {
        $this->actingAs($this->user);
        $caregiver = Caregiver::factory()->create();
        $this->client->favoriteCaregivers()->attach($caregiver->id);

        $response = $this->patch(route('clients.update', $this->client), [
            'first_name' => $this->client->first_name,
            'last_name' => $this->client->last_name,
            'phone' => $this->client->phone,
            'client_type' => $this->client->client_type,
            'favorite_caregiver_ids' => [],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('client_favorite_caregivers', [
            'client_id' => $this->client->id,
            'caregiver_id' => $caregiver->id,
        ]);
    });
});
