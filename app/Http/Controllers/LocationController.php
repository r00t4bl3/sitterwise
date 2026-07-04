<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLocationRequest;
use App\Http\Requests\UpdateLocationRequest;
use App\Models\Location;
use App\Models\ZipCode;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class LocationController extends Controller
{
    public function index(): Response
    {
        $locations = Location::orderBy('name')->get()
            ->map(fn (Location $location) => [
                'id' => $location->id,
                'name' => $location->name,
                'svg_icon' => $location->svg_icon,
                'is_active' => $location->is_active,
            ]);

        $zipCodes = ZipCode::with('location:id,name')
            ->orderBy('zip_code')
            ->get()
            ->map(fn (ZipCode $zip) => [
                'id' => $zip->id,
                'zip_code' => $zip->zip_code,
                'area' => $zip->area,
                'location_id' => $zip->location_id,
                'location_name' => $zip->location?->name,
            ]);

        return Inertia::render('superadmin/locations/index', [
            'locations' => $locations,
            'zipCodes' => $zipCodes,
        ]);
    }

    public function store(StoreLocationRequest $request): RedirectResponse
    {
        Location::create($request->validated());

        return redirect()->route('locations.index')
            ->with('success', 'Location created successfully');
    }

    public function update(UpdateLocationRequest $request, Location $location): RedirectResponse
    {
        $location->update($request->validated());

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
