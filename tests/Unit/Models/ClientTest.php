<?php

use App\Models\Caregiver;
use App\Models\Client;
use App\Models\ClientAddress;
use App\Models\ClientChild;
use App\Models\ClientPet;
use App\Models\User;
use Database\Seeders\AttributeDefinitionSeeder;
use Database\Seeders\CertificationTypeSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\SpecialtyTypeSeeder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('can be instantiated', function () {
    $client = Client::factory()->make();

    $this->assertInstanceOf(Client::class, $client);
});

test('has correct fillable fields', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create([
        'user_id' => $user->id,
        'first_name' => 'John',
        'last_name' => 'Doe',
        'phone' => '123-456-7890',
        'client_type' => 'vacationer',
        'how_did_you_hear' => 'google',
    ]);

    $this->assertEquals('John', $client->first_name);
    $this->assertEquals('Doe', $client->last_name);
    $this->assertEquals('123-456-7890', $client->phone);
    $this->assertEquals('vacationer', $client->client_type);
    $this->assertEquals('google', $client->how_did_you_hear);
});

test('casts sitter preferences as array', function () {
    $client = Client::factory()->create([
        'sitter_preferences' => ['pref1' => 'value1', 'pref2' => 'value2'],
    ]);

    $this->assertIsArray($client->sitter_preferences);
    $this->assertArrayHasKey('pref1', $client->sitter_preferences);
    $this->assertEquals('value1', $client->sitter_preferences['pref1']);
});

test('defines user relationship', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create(['user_id' => $user->id]);

    $this->assertInstanceOf(User::class, $client->user);
    $this->assertEquals($user->id, $client->user->id);
});

test('defines addresses relationship', function () {
    $client = Client::factory()->create();
    $address = ClientAddress::factory()->create(['client_id' => $client->id]);

    $this->assertTrue($client->addresses->contains($address));
});

test('defines children relationship', function () {
    $client = Client::factory()->create();
    $child = ClientChild::factory()->create(['client_id' => $client->id]);

    $this->assertTrue($client->children->contains($child));
});

test('defines pets relationship', function () {
    $client = Client::factory()->create();
    $pet = ClientPet::factory()->create(['client_id' => $client->id]);

    $this->assertTrue($client->pets->contains($pet));
});

test('defines attribute definitions relationship', function () {
    $client = Client::factory()->make();
    $relation = $client->attributes();

    $this->assertInstanceOf(BelongsToMany::class, $relation);
});

test('returns full name', function () {
    $client = Client::factory()->make([
        'first_name' => 'John',
        'last_name' => 'Doe',
    ]);

    $this->assertEquals('John Doe', $client->full_name);
});

test('special needs is inferred from notes', function () {
    $clientWithNotes = Client::factory()->create([
        'special_needs_notes' => 'Client requires special accommodations',
    ]);
    $this->assertTrue($clientWithNotes->special_needs);

    $clientWithoutNotes = Client::factory()->create([
        'special_needs_notes' => null,
    ]);
    $this->assertFalse($clientWithoutNotes->special_needs);
});

test('has special needs notes field', function () {
    $client = Client::factory()->create([
        'special_needs_notes' => 'Client requires special accommodations',
    ]);

    $this->assertEquals('Client requires special accommodations', $client->special_needs_notes);
});

test('defines favorite caregivers relationship', function () {
    $client = Client::factory()->create();
    $relation = $client->favoriteCaregivers();

    $this->assertInstanceOf(BelongsToMany::class, $relation);
});

test('defines blocked caregivers relationship', function () {
    $client = Client::factory()->create();
    $relation = $client->blockedCaregivers();

    $this->assertInstanceOf(BelongsToMany::class, $relation);
});

test('defines previous caregivers relationship', function () {
    $client = Client::factory()->create();
    $relation = $client->previousCaregivers();

    $this->assertInstanceOf(BelongsToMany::class, $relation);
});

test('favorite caregivers syncs correctly', function () {
    $this->seed([
        CertificationTypeSeeder::class,
        SpecialtyTypeSeeder::class,
        LocationSeeder::class,
        AttributeDefinitionSeeder::class,
    ]);
    $client = Client::factory()->create();
    $caregivers = Caregiver::factory()->count(3)->create();

    $client->favoriteCaregivers()->sync([$caregivers[0]->id, $caregivers[1]->id]);

    $this->assertCount(2, $client->favoriteCaregivers);
    $this->assertTrue($client->favoriteCaregivers->contains($caregivers[0]));
});

test('blocked caregivers syncs correctly', function () {
    $this->seed([
        CertificationTypeSeeder::class,
        SpecialtyTypeSeeder::class,
        LocationSeeder::class,
        AttributeDefinitionSeeder::class,
    ]);
    $client = Client::factory()->create();
    $caregiver = Caregiver::factory()->create();

    $client->blockedCaregivers()->attach($caregiver->id);

    $this->assertCount(1, $client->blockedCaregivers);
});
