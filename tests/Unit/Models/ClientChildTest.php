<?php

use App\Models\Client;
use App\Models\ClientChild;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('can be instantiated', function () {
    $child = ClientChild::factory()->make();

    $this->assertInstanceOf(ClientChild::class, $child);
});

test('has correct fillable fields', function () {
    $client = Client::factory()->create();
    $child = ClientChild::factory()->create([
        'client_id' => $client->id,
        'name' => 'Emma',
        'gender' => 'female',
        'birth_date' => '2018-05-01',
    ]);

    $this->assertEquals('Emma', $child->name);
    $this->assertEquals('female', $child->gender);
    $this->assertEquals(5, $child->birth_month);
    $this->assertEquals(2018, $child->birth_year);
});

test('defines client relationship', function () {
    $client = Client::factory()->create();
    $child = ClientChild::factory()->create(['client_id' => $client->id]);

    $this->assertInstanceOf(Client::class, $child->client);
    $this->assertEquals($client->id, $child->client->id);
});

test('returns age when birth data available', function () {
    $child = ClientChild::factory()->create([
        'birth_date' => '2019-05-01',
    ]);

    $this->assertIsInt($child->age);
    $this->assertGreaterThanOrEqual(4, $child->age);
});

test('returns null age when birth data missing', function () {
    $child = ClientChild::factory()->make([
        'birth_date' => null,
    ]);

    $this->assertNull($child->age);
});
