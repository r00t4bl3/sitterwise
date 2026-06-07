<?php

namespace App\Http\Controllers;

use App\Enums\SanDiegoCity;
use App\Http\Requests\StoreLocationRequest;
use App\Http\Requests\UpdateLocationRequest;
use App\Models\Location;
use App\Services\CaregiverRecommendation\LocationMatcher;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class LocationController extends Controller
{
    public function __construct(
        protected LocationMatcher $locationMatcher,
    ) {}

    public function index(): Response
    {
        $locations = Location::orderBy('name')->get()
            ->map(fn (Location $location) => [
                'id' => $location->id,
                'name' => $location->name,
                'svg_icon' => $location->svg_icon,
                'is_active' => $location->is_active,
                'cities' => $this->locationMatcher->parseCities($location),
            ]);

        return Inertia::render('superadmin/locations/index', [
            'locations' => $locations,
            'knownCities' => SanDiegoCity::labels(),
        ]);
    }

    public function store(StoreLocationRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $cities = $validated['cities'] ?? [];
        unset($validated['cities']);

        $location = Location::create($validated);

        if (! empty($cities)) {
            $this->locationMatcher->updateLocationCities($location, $cities);
        }

        return redirect()->route('locations.index')
            ->with('success', 'Location created successfully');
    }

    public function update(UpdateLocationRequest $request, Location $location): RedirectResponse
    {
        $validated = $request->validated();
        $cities = $validated['cities'] ?? [];
        unset($validated['cities']);

        $location->update($validated);
        $this->locationMatcher->updateLocationCities($location, $cities);

        return redirect()->route('locations.index')
            ->with('success', 'Location updated successfully');
    }

    public function destroy(Location $location): RedirectResponse
    {
        $location->delete();

        return redirect()->route('locations.index')
            ->with('success', 'Location deleted successfully');
    }
}
