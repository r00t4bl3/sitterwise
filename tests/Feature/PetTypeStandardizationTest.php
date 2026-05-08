<?php

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->clientUser = User::factory()->create(['role' => 'client']);
    $this->client = Client::factory()->create(['user_id' => $this->clientUser->id]);
});

it('passes pet_types to the admin dashboard', function () {
    $response = $this->actingAs($this->admin)->get('/dashboard');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('admin.pet_types', 3)
        ->where('admin.pet_types.0.value', 'dog')
        ->where('admin.pet_types.0.label', 'Dog')
    );
});

it('passes pet_types to the admin bookings index', function () {
    $response = $this->actingAs($this->admin)->get('/bookings');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('pet_types', 3)
        ->where('pet_types.0.value', 'dog')
    );
});

it('passes pet_types to the admin client edit page', function () {
    $response = $this->actingAs($this->admin)->get(route('clients.edit', $this->client));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('pet_types', 3)
        ->where('pet_types.0.value', 'dog')
    );
});

it('passes pet_types to the client booking create page', function () {
    $response = $this->actingAs($this->clientUser)->get('/bookings/create');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('pet_types', 3)
        ->where('pet_types.0.value', 'dog')
    );
});

it('passes pet_types to the guest booking create page', function () {
    $response = $this->get('/book');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('pet_types', 3)
        ->where('pet_types.0.value', 'dog')
    );
});
