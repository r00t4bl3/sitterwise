<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\ZipCode;
use Illuminate\Database\Seeder;

class ZipCodeSeeder extends Seeder
{
    /**
     * Seed zip codes (zip -> neighborhood -> region) from the curated
     * San Diego dataset. Idempotent: safe to re-run. Regions are matched (or
     * created) by trimmed name so stray whitespace in the CSV doesn't spawn
     * duplicate Location rows.
     */
    public function run(): void
    {
        $path = database_path('seeders/data/san_diego.csv');

        if (! is_readable($path)) {
            $this->command?->warn("Zip code dataset not found at {$path}; skipping.");

            return;
        }

        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);

            return;
        }

        $columns = array_map(fn ($c) => trim((string) $c), $header);
        $zipIdx = array_search('zip_code', $columns, true);
        $areaIdx = array_search('area', $columns, true);
        $regionIdx = array_search('county_region', $columns, true);

        if ($zipIdx === false || $regionIdx === false) {
            fclose($handle);
            $this->command?->warn('Zip code dataset is missing required columns; skipping.');

            return;
        }

        $regionCache = [];
        $count = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $zip = trim((string) ($row[$zipIdx] ?? ''));
            $region = trim((string) ($row[$regionIdx] ?? ''));
            $area = $areaIdx !== false ? trim((string) ($row[$areaIdx] ?? '')) : null;

            if ($zip === '' || $region === '') {
                continue;
            }

            $locationId = $regionCache[$region] ??= Location::firstOrCreate(['name' => $region])->id;

            ZipCode::updateOrCreate(
                ['zip_code' => $zip],
                ['area' => $area ?: null, 'location_id' => $locationId],
            );

            $count++;
        }

        fclose($handle);

        $this->command?->info("Seeded {$count} zip codes.");
    }
}
