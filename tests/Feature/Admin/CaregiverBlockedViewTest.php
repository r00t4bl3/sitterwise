<?php

use App\Models\Caregiver;
use App\Models\Client;
use App\Models\User;
use Database\Seeders\AttributeDefinitionSeeder;
use Database\Seeders\CertificationTypeSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\SpecialtyTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        SpecialtyTypeSeeder::class,
        LocationSeeder::class,
        CertificationTypeSeeder::class,
        AttributeDefinitionSeeder::class,
    ]);
    $this->admin = User::factory()->create(['role' => 'admin']);
});

test('admin caregivers index exposes a blocked-by count per caregiver (#94)', function () {
    $caregiver = Caregiver::factory()->create();
    Client::factory()->count(2)->create()->each(
        fn (Client $client) => $client->blockedCaregivers()->attach($caregiver->id),
    );

    $this->actingAs($this->admin)->get('/caregivers')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/caregivers/index')
            ->where('caregivers.data.0.blocked_clients_count', 2)
            ->where('filters.blocked', false)
        );
});

test('the blocked filter returns only caregivers with at least one block (#94)', function () {
    $blocked = Caregiver::factory()->create();
    $notBlocked = Caregiver::factory()->create();
    Client::factory()->create()->blockedCaregivers()->attach($blocked->id);

    $this->actingAs($this->admin)->get('/caregivers?blocked=1')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('caregivers.total', 1)
            ->where('caregivers.data.0.id', $blocked->id)
            ->where('filters.blocked', true)
        );

    expect($notBlocked->blockedClients()->count())->toBe(0);
});

test('caregivers can be sorted by blocked-by count (#94)', function () {
    $most = Caregiver::factory()->create();
    $few = Caregiver::factory()->create();

    Client::factory()->count(3)->create()->each(
        fn (Client $client) => $client->blockedCaregivers()->attach($most->id),
    );
    Client::factory()->create()->blockedCaregivers()->attach($few->id);

    $this->actingAs($this->admin)
        ->get('/caregivers?sort=blocked_clients_count&direction=desc')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('caregivers.data.0.id', $most->id)
            ->where('caregivers.data.0.blocked_clients_count', 3)
            ->where('caregivers.data.1.id', $few->id)
            ->where('caregivers.data.1.blocked_clients_count', 1)
        );
});
