<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\SpecialtyType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AvailabilityPreferencesController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user->isCaregiver()) {
            return redirect()->route('profile.edit');
        }

        $caregiver = $user->caregiver->load(['specialtyTypes', 'locations']);

        return Inertia::render('settings/availability-preferences', [
            'specialtyTypes' => SpecialtyType::active()->get(['id', 'name']),
            'locations' => Location::active()->get(['id', 'name']),
            'selectedSpecialtyIds' => $caregiver->specialtyTypes->pluck('id')->all(),
            'selectedLocationIds' => $caregiver->locations->pluck('id')->all(),
            'preferredLocationId' => $caregiver->locations()
                ->wherePivot('is_preferred', true)
                ->first()?->id,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->isCaregiver()) {
            return redirect()->route('profile.edit');
        }

        $validated = $request->validate([
            'specialty_type_ids' => 'nullable|array',
            'specialty_type_ids.*' => 'exists:specialty_types,id',
            'location_ids' => 'nullable|array',
            'location_ids.*' => 'exists:locations,id',
            'preferred_location_id' => 'nullable|exists:locations,id',
        ]);

        $caregiver = $user->caregiver;

        $caregiver->specialtyTypes()->sync($validated['specialty_type_ids'] ?? []);

        // Mirror the admin form: the one matching preferred_location_id is flagged
        // preferred; the rest are "willing". This is exactly what the recommendation
        // matcher reads (specialtyTypes names + locations.is_preferred).
        $locationSync = [];
        foreach ($validated['location_ids'] ?? [] as $locationId) {
            $locationSync[$locationId] = [
                'is_preferred' => $locationId == ($validated['preferred_location_id'] ?? null),
            ];
        }
        $caregiver->locations()->sync($locationSync);

        return redirect()->route('settings.caregiver.availability')
            ->with('success', 'Your availability preferences have been updated.');
    }
}
