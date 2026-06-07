<?php

namespace App\Services\CaregiverRecommendation;

use App\Models\Location;
use Illuminate\Support\Collection;

class LocationMatcher
{
    protected ?Collection $cityMap = null;

    /**
     * Get the location ID for a given city by matching against
     * the city-to-location map built from active locations.
     */
    public function getLocationIdForCity(?string $city): ?int
    {
        if (blank($city)) {
            return null;
        }

        $city = strtolower(trim($city));

        return $this->getCityMap()->get($city);
    }

    /**
     * Extract the city from a booking context.
     * The booking's address_city or the hotel's city.
     */
    public function getBookingCity(?string $addressCity, ?string $hotelCity = null): ?string
    {
        if (! blank($addressCity)) {
            return trim($addressCity);
        }

        if (! blank($hotelCity)) {
            return trim($hotelCity);
        }

        return null;
    }

    /**
     * Parse a Location's cities string into an array of city names.
     */
    public function parseCities(Location $location): array
    {
        if (blank($location->cities)) {
            return [];
        }

        return collect(explode(',', $location->cities))
            ->map(fn ($city) => trim($city))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Get all cities grouped by location ID.
     */
    public function getAllCitiesGrouped(): Collection
    {
        return $this->getLocations()->mapWithKeys(fn (Location $location) => [
            $location->id => $this->parseCities($location),
        ]);
    }

    /**
     * Update a location's cities string from an array of city names.
     */
    public function updateLocationCities(Location $location, array $cities): void
    {
        $cities = collect($cities)
            ->map(fn ($city) => trim($city))
            ->filter()
            ->unique()
            ->values();

        $description = $cities->isNotEmpty() ? $cities->implode(', ') : null;

        $location->update(['cities' => $description]);

        $this->cityMap = null;
    }

    /**
     * Build a map of lowercase city name → location ID.
     */
    protected function getCityMap(): Collection
    {
        if ($this->cityMap !== null) {
            return $this->cityMap;
        }

        $map = collect();

        foreach ($this->getLocations() as $location) {
            foreach ($this->parseCities($location) as $city) {
                $map->put(strtolower($city), $location->id);
            }
        }

        return $this->cityMap = $map;
    }

    protected function getLocations(): Collection
    {
        return Location::where('is_active', true)->get();
    }
}
