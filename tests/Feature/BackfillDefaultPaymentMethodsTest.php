<?php

use App\Models\Client;
use App\Models\ClientPaymentMethod;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

it('dry run does not change anything', function () {
    $user = User::factory()->create(['role' => 'client']);
    $client = Client::factory()->for($user)->create();

    ClientPaymentMethod::factory()->create([
        'client_id' => $client->id,
        'is_default' => false,
        'status' => 'active',
    ]);

    $this->artisan('clients:backfill-default-payment-method')
        ->expectsOutputToContain('DRY RUN')
        ->assertExitCode(0);

    expect(ClientPaymentMethod::where('client_id', $client->id)->where('is_default', true)->exists())
        ->toBeFalse();
});

it('with --apply marks the most recently added active method as default', function () {
    $user = User::factory()->create(['role' => 'client']);
    $client = Client::factory()->for($user)->create();

    $older = ClientPaymentMethod::factory()->create([
        'client_id' => $client->id,
        'is_default' => false,
        'status' => 'active',
        'created_at' => now()->subDay(),
    ]);
    $latest = ClientPaymentMethod::factory()->create([
        'client_id' => $client->id,
        'is_default' => false,
        'status' => 'active',
        'created_at' => now(),
    ]);

    $this->artisan('clients:backfill-default-payment-method --apply')->assertExitCode(0);

    expect($latest->refresh()->is_default)->toBeTrue();
    expect($older->refresh()->is_default)->toBeFalse();
});

it('does not touch a client that already has a default', function () {
    $user = User::factory()->create(['role' => 'client']);
    $client = Client::factory()->for($user)->create();

    $default = ClientPaymentMethod::factory()->create([
        'client_id' => $client->id,
        'is_default' => true,
        'status' => 'active',
    ]);
    $other = ClientPaymentMethod::factory()->create([
        'client_id' => $client->id,
        'is_default' => false,
        'status' => 'active',
        'created_at' => now(),
    ]);

    $this->artisan('clients:backfill-default-payment-method --apply')->assertExitCode(0);

    expect($default->refresh()->is_default)->toBeTrue();
    expect($other->refresh()->is_default)->toBeFalse();
});
