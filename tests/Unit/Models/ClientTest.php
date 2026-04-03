<?php

use App\Models\Client;
use App\Models\ClientAddress;
use App\Models\ClientChild;
use App\Models\ClientPet;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_be_instantiated()
    {
        $client = Client::factory()->make();

        $this->assertInstanceOf(Client::class, $client);
    }

    public function test_has_correct_fillable_fields()
    {
        $user = User::factory()->create();
        $client = Client::factory()->create([
            'user_id' => $user->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'cell_phone' => '123-456-7890',
            'client_type' => 'sd_resident',
            'how_did_you_hear' => 'google',
        ]);

        $this->assertEquals('John', $client->first_name);
        $this->assertEquals('Doe', $client->last_name);
        $this->assertEquals('john@example.com', $client->email);
        $this->assertEquals('123-456-7890', $client->cell_phone);
        $this->assertEquals('sd_resident', $client->client_type);
        $this->assertEquals('google', $client->how_did_you_hear);
    }

    public function test_casts_sitter_preferences_as_array()
    {
        $client = Client::factory()->create([
            'sitter_preferences' => ['pref1' => 'value1', 'pref2' => 'value2'],
        ]);

        $this->assertIsArray($client->sitter_preferences);
        $this->assertArrayHasKey('pref1', $client->sitter_preferences);
        $this->assertEquals('value1', $client->sitter_preferences['pref1']);
    }

    public function test_defines_user_relationship()
    {
        $user = User::factory()->create();
        $client = Client::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $client->user);
        $this->assertEquals($user->id, $client->user->id);
    }

    public function test_defines_addresses_relationship()
    {
        $client = Client::factory()->create();
        $address = ClientAddress::factory()->create(['client_id' => $client->id]);

        $this->assertTrue($client->addresses->contains($address));
    }

    public function test_defines_children_relationship()
    {
        $client = Client::factory()->create();
        $child = ClientChild::factory()->create(['client_id' => $client->id]);

        $this->assertTrue($client->children->contains($child));
    }

    public function test_defines_pets_relationship()
    {
        $client = Client::factory()->create();
        $pet = ClientPet::factory()->create(['client_id' => $client->id]);

        $this->assertTrue($client->pets->contains($pet));
    }

    public function test_defines_attribute_definitions_relationship()
    {
        $client = Client::factory()->make();
        $relation = $client->attributes();

        $this->assertInstanceOf(BelongsToMany::class, $relation);
    }

    public function test_returns_full_name()
    {
        $client = Client::factory()->make([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $this->assertEquals('John Doe', $client->full_name);
    }

    public function test_casts_special_needs_as_boolean()
    {
        $client = Client::factory()->create(['special_needs' => true]);

        $this->assertTrue($client->special_needs);
        $this->assertIsBool($client->special_needs);
    }

    public function test_has_special_needs_notes_field()
    {
        $client = Client::factory()->create([
            'special_needs_notes' => 'Client requires special accommodations',
        ]);

        $this->assertEquals('Client requires special accommodations', $client->special_needs_notes);
    }
}
