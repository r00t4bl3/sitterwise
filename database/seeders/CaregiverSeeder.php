<?php

namespace Database\Seeders;

use App\Models\Caregiver;
use App\Models\User;
use Illuminate\Database\Seeder;

class CaregiverSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::factory()->count(51)->create();

        foreach ($users as $user) {
            $user->role = 'caregiver';
            $user->save();

            Caregiver::factory()->create([
                'user_id' => $user->id,
            ]);
        }
    }
}
