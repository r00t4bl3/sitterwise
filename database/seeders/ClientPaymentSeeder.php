<?php

namespace Database\Seeders;

use App\Models\ClientPayment;
use Illuminate\Database\Seeder;

class ClientPaymentSeeder extends Seeder
{
    public function run(): void
    {
        ClientPayment::factory()->count(30)->create();
    }
}
