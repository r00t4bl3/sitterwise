<?php

use App\Models\Client;
use App\Models\ClientAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientAddressTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_instantiated()
    {
        $address = ClientAddress::factory()->make();

        $this->assertInstanceOf(ClientAddress::class, $address);
    }

    public function test_has_correct_fillable_fields()
    {
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
    }

    public function test_casts_is_primary_as_boolean()
    {
        $address = ClientAddress::factory()->create(['is_primary' => true]);

        $this->assertTrue($address->is_primary);
        $this->assertIsBool($address->is_primary);
    }

    public function test_defines_client_relationship()
    {
        $client = Client::factory()->create();
        $address = ClientAddress::factory()->create(['client_id' => $client->id]);

        $this->assertInstanceOf(Client::class, $address->client);
        $this->assertEquals($client->id, $address->client->id);
    }
}
