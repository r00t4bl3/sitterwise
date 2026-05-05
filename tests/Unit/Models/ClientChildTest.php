<?php

use App\Models\Client;
use App\Models\ClientChild;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientChildTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_instantiated()
    {
        $child = ClientChild::factory()->make();

        $this->assertInstanceOf(ClientChild::class, $child);
    }

    public function test_has_correct_fillable_fields()
    {
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
    }

    public function test_defines_client_relationship()
    {
        $client = Client::factory()->create();
        $child = ClientChild::factory()->create(['client_id' => $client->id]);

        $this->assertInstanceOf(Client::class, $child->client);
        $this->assertEquals($client->id, $child->client->id);
    }

    public function test_returns_age_when_birth_data_available()
    {
        $child = ClientChild::factory()->create([
            'birth_date' => '2019-05-01',
        ]);

        $this->assertIsInt($child->age);
        $this->assertGreaterThanOrEqual(4, $child->age);
    }

    public function test_returns_null_age_when_birth_data_missing()
    {
        $child = ClientChild::factory()->make([
            'birth_date' => null,
        ]);

        $this->assertNull($child->age);
    }
}
