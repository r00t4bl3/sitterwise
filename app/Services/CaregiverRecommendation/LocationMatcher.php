<?php

namespace App\Services\CaregiverRecommendation;

use App\Models\ZipCode;
use Illuminate\Support\Collection;

class LocationMatcher
{
    protected ?Collection $zipMap = null;

    /**
     * Normalize a raw zip to its 5-digit form (handles ZIP+4 and stray
     * characters). Returns null when there aren't 5 usable digits.
     */
    public function normalizeZip(?string $zip): ?string
    {
        if (blank($zip)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $zip);

        return strlen((string) $digits) >= 5 ? substr((string) $digits, 0, 5) : null;
    }

    /**
     * Get the region (Location) ID for a given zip code, or null if the zip is
     * blank/unrecognized/unassigned.
     */
    public function getLocationIdForZip(?string $zip): ?int
    {
        $zip = $this->normalizeZip($zip);

        if ($zip === null) {
            return null;
        }

        return $this->getZipMap()->get($zip)['location_id'] ?? null;
    }

    /**
     * Get the neighborhood ("area of town") for a given zip code, or null.
     */
    public function getAreaForZip(?string $zip): ?string
    {
        $zip = $this->normalizeZip($zip);

        if ($zip === null) {
            return null;
        }

        return $this->getZipMap()->get($zip)['area'] ?? null;
    }

    /**
     * Clear the cached zip map (call after zip assignments change).
     */
    public function flush(): void
    {
        $this->zipMap = null;
    }

    /**
     * Build a map of normalized zip → ['location_id' => int|null, 'area' => string|null].
     */
    protected function getZipMap(): Collection
    {
        if ($this->zipMap !== null) {
            return $this->zipMap;
        }

        $map = collect();

        foreach (ZipCode::all(['zip_code', 'area', 'location_id']) as $zipCode) {
            $normalized = $this->normalizeZip($zipCode->zip_code);

            if ($normalized === null) {
                continue;
            }

            $map->put($normalized, [
                'location_id' => $zipCode->location_id,
                'area' => $zipCode->area,
            ]);
        }

        return $this->zipMap = $map;
    }
}
