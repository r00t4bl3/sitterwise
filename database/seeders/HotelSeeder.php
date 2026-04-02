<?php
namespace Database\Seeders;

use App\Models\Hotel;
use Illuminate\Database\Seeder;

class HotelSeeder extends Seeder
{
    public function run(): void
    {
        $csvPath = database_path('seeders/data/hotels.csv');

        $handle = fopen($csvPath, 'r');

        fgetcsv($handle, 0, ',', '"', '');

        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $hourlyRate = $this->determineHourlyRate($row[3]);

            Hotel::factory()->create([
                'name'        => $row[0],
                'line1'       => $row[1],
                'line2'       => $row[2] ?: null,
                'city'        => $row[3],
                'state'       => $row[4],
                'zip'         => $row[5],
                'hourly_rate' => $hourlyRate,
            ]);
        }

        fclose($handle);
    }

    private function determineHourlyRate(string $city): float
    {
        return match ($city) {
            'La Jolla', 'Del Mar', 'Rancho Santa Fe' => 28.00,
            'Coronado', 'Carlsbad'   => 22.00,
            'Oceanside', 'Escondido' => 18.00,
            default => 22.00,
        };
    }
}