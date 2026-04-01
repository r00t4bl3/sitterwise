<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TimeSlot;
use App\Http\Controllers\Controller;
use App\Models\Availability;
use App\Models\Caregiver;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CaregiverAvailabilityController extends Controller
{
    public function index(Caregiver $caregiver)
    {
        $availabilities = $caregiver->availabilities()
            ->inTheFuture()
            ->orderBy('date')
            ->get()
            ->map(function ($availability) {
                return [
                    'id' => $availability->id,
                    'date' => $availability->date->format('Y-m-d'),
                    'time_slots' => $availability->time_slots,
                    'specific_time' => $availability->specific_time,
                ];
            });

        return inertia('availabilities/show', [
            'caregiver' => $caregiver->load(['user', 'status', 'locations', 'specialtyTypes']),
            'availabilities' => $availabilities,
            'timeSlots' => array_map(
                fn ($case) => ['value' => $case->value, 'label' => $case->label()],
                TimeSlot::cases()
            ),
        ]);
    }

    public function store(Request $request, Caregiver $caregiver)
    {
        $validated = $request->validate([
            'date' => 'required|date|after_or_equal:today',
            'time_slots' => 'required|array|min:1',
            'time_slots.*' => 'required|in:morning,afternoon,evening',
            'specific_time' => 'nullable|string|max:255',
        ]);

        Availability::updateOrCreate(
            [
                'caregiver_id' => $caregiver->id,
                'date' => Carbon::parse($validated['date'])->toDateTimeString(),
            ],
            [
                'time_slots' => $validated['time_slots'],
                'specific_time' => $validated['specific_time'] ?? null,
            ]
        );

        return back()->with('success', 'Availability saved successfully.');
    }

    public function update(Request $request, Caregiver $caregiver, Availability $availability)
    {
        $validated = $request->validate([
            'date' => 'required|date|after_or_equal:today',
            'time_slots' => 'required|array|min:1',
            'time_slots.*' => 'required|in:morning,afternoon,evening',
            'specific_time' => 'nullable|string|max:255',
        ]);

        $availability->update([
            'date' => Carbon::parse($validated['date'])->toDateTimeString(),
            'time_slots' => $validated['time_slots'],
            'specific_time' => $validated['specific_time'] ?? null,
        ]);

        return back()->with('success', 'Availability updated successfully.');
    }

    public function destroy(Caregiver $caregiver, Availability $availability)
    {
        $availability->delete();

        return back()->with('success', 'Availability deleted successfully.');
    }
}
