<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\ClientAddress;
use App\Models\ClientChild;
use App\Models\ClientPet;
use App\Models\User;
use Illuminate\Database\Seeder;

class ClientSeeder extends Seeder
{
    public function run(): void
    {
        for ($i = 0; $i < 21; $i++) {
            $user = User::factory()->create([
                'role' => 'client',
            ]);

            $client = Client::factory()->create([
                'user_id' => $user->id,
            ]);

            if (fake()->boolean(70)) {
                ClientAddress::factory()->create([
                    'client_id' => $client->id,
                    'is_primary' => true,
                ]);
            }

            if (fake()->boolean(60)) {
                $childCount = fake()->numberBetween(1, 3);
                for ($j = 0; $j < $childCount; $j++) {
                    ClientChild::factory()->create([
                        'client_id' => $client->id,
                    ]);
                }
            }

            if (fake()->boolean(40)) {
                $petCount = fake()->numberBetween(1, 2);
                for ($k = 0; $k < $petCount; $k++) {
                    ClientPet::factory()->create([
                        'client_id' => $client->id,
                    ]);
                }
            }
        }
    }
}
