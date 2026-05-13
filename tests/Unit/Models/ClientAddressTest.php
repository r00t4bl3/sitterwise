<?php

use App\Models\Client;
use App\Models\ClientAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('can be instantiated', function () {
    $address = ClientAddress::factory()->make();

    $this->assertInstanceOf(ClientAddress::class, $address);
});

test('has correct fillable fields', function () {
    $client = Client::factory()->create();
    $address = ClientAddress::factory()->create([
        'client_id' => $client->id,
        'label' => 'Home',
        'location_type' => 'hotel',
        'line1' => '123 Main St',
        'line2' => 'Apt 4B',
        'city' => 'San Diego',
        'state' => 'CA',
        'zip' => '92101',
        'is_primary' => true,
    ]);

    $this->assertEquals('Home', $address->label);
    $this->assertEquals('hotel', $address->location_type);
    $this->assertEquals('123 Main St', $address->line1);
    $this->assertEquals('Apt 4B', $address->line2);
    $this->assertEquals('San Diego', $address->city);
    $this->assertEquals('CA', $address->state);
    $this->assertEquals('92101', $address->zip);
    $this->assertTrue($address->is_primary);
});

test('casts is primary as boolean', function () {
    $address = ClientAddress::factory()->create(['is_primary' => true]);

    $this->assertTrue($address->is_primary);
    $this->assertIsBool($address->is_primary);
});

test('defines client relationship', function () {
    $client = Client::factory()->create();
    $address = ClientAddress::factory()->create(['client_id' => $client->id]);

    $this->assertInstanceOf(Client::class, $address->client);
    $this->assertEquals($client->id, $address->client->id);
});
