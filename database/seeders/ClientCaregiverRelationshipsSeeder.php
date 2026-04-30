<?php

namespace Database\Seeders;

use App\Models\Caregiver;
use App\Models\Client;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClientCaregiverRelationshipsSeeder extends Seeder
{
    public function run(): void
    {
        $clients = Client::all();
        $caregivers = Caregiver::all();

        if ($caregivers->isEmpty() || $clients->isEmpty()) {
            $this->command->error('No clients or caregivers found. Run ClientSeeder and CaregiverSeeder first.');

            return;
        }

        foreach ($clients as $client) {
            $favoriteCount = min(rand(2, 4), $caregivers->count());
            $favoriteCaregivers = $caregivers->random($favoriteCount);

            foreach ($favoriteCaregivers as $caregiver) {
                DB::table('client_favorite_caregivers')->insertOrIgnore([
                    'client_id' => $client->id,
                    'caregiver_id' => $caregiver->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $availableForBlock = $caregivers->whereNotIn('id', $favoriteCaregivers->pluck('id'));
            if ($availableForBlock->isNotEmpty()) {
                $blockedCount = min(rand(1, 3), $availableForBlock->count());
                $blockedCaregivers = $availableForBlock->random($blockedCount);

                foreach ($blockedCaregivers as $caregiver) {
                    DB::table('client_blocked_caregivers')->insertOrIgnore([
                        'client_id' => $client->id,
                        'caregiver_id' => $caregiver->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        $this->command->info('Client-caregiver relationships seeded successfully.');
    }
}
