<?php

use App\Models\Client;
use App\Models\ClientPet;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('can be instantiated', function () {
    $pet = ClientPet::factory()->make();

    $this->assertInstanceOf(ClientPet::class, $pet);
});

test('has correct fillable fields', function () {
    $client = Client::factory()->create();
    $pet = ClientPet::factory()->create([
        'client_id' => $client->id,
        'name' => 'Buddy',
        'type' => 'dog',
        'breed' => 'Golden Retriever',
        'notes' => 'Friendly and well-trained',
    ]);

    $this->assertEquals('Buddy', $pet->name);
    $this->assertEquals('dog', $pet->type);
    $this->assertEquals('Golden Retriever', $pet->breed);
    $this->assertEquals('Friendly and well-trained', $pet->notes);
});

test('defines client relationship', function () {
    $client = Client::factory()->create();
    $pet = ClientPet::factory()->create(['client_id' => $client->id]);

    $this->assertInstanceOf(Client::class, $pet->client);
    $this->assertEquals($client->id, $pet->client->id);
});
