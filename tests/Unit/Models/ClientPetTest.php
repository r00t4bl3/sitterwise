<?php

use App\Models\Client;
use App\Models\ClientPet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientPetTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_instantiated()
    {
        $pet = ClientPet::factory()->make();

        $this->assertInstanceOf(ClientPet::class, $pet);
    }

    public function test_has_correct_fillable_fields()
    {
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
    }

    public function test_defines_client_relationship()
    {
        $client = Client::factory()->create();
        $pet = ClientPet::factory()->create(['client_id' => $client->id]);

        $this->assertInstanceOf(Client::class, $pet->client);
        $this->assertEquals($client->id, $pet->client->id);
    }
}
