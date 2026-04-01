<?php

namespace App\Services\Availability;

use App\Enums\TimeSlot;
use App\Models\Availability;
use App\Services\Availability\Contracts\AvailabilityServiceInterface;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CaregiverAvailabilityService implements AvailabilityServiceInterface
{
    public function index()
    {
        $user = auth()->user();

        if (! $user->caregiver) {
            return redirect('/dashboard')->with('error', 'You are not a caregiver.');
        }

        $perPage = 20;

        $availabilities = Availability::where('caregiver_id', $user->caregiver->id)
            ->where('date', '>=', now()->toDateString())
            ->orderBy('date')
            ->paginate($perPage);

        return Inertia::render('availabilities/my', [
            'availabilities' => $availabilities,
            'timeSlots' => array_map(
                fn ($case) => ['value' => $case->value, 'label' => $case->label()],
                TimeSlot::cases()
            ),
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        if (! $user->caregiver) {
            return back()->with('error', 'You are not a caregiver.');
        }

        $validated = $request->validate([
            'date' => 'required|date|after_or_equal:today',
            'time_slots' => 'required|array|min:1',
            'time_slots.*' => 'in:morning,afternoon,evening',
            'specific_time' => 'nullable|string|max:255',
        ]);

        Availability::updateOrCreate(
            [
                'caregiver_id' => $user->caregiver->id,
                'date' => Carbon::parse($validated['date'])->toDateTimeString(),
            ],
            [
                'time_slots' => $validated['time_slots'],
                'specific_time' => $validated['specific_time'] ?? null,
            ]
        );

        return back()->with('success', 'Availability saved successfully.');
    }

    public function update(Request $request, $id)
    {
        $user = auth()->user();

        if (! $user->caregiver) {
            return back()->with('error', 'You are not a caregiver.');
        }

        $validated = $request->validate([
            'date' => 'required|date|after_or_equal:today',
            'time_slots' => 'required|array|min:1',
            'time_slots.*' => 'in:morning,afternoon,evening',
            'specific_time' => 'nullable|string|max:255',
        ]);

        $availability = Availability::where('caregiver_id', $user->caregiver->id)
            ->findOrFail($id);

        $availability->update([
            'date' => Carbon::parse($validated['date'])->toDateTimeString(),
            'time_slots' => $validated['time_slots'],
            'specific_time' => $validated['specific_time'] ?? null,
        ]);

        return back()->with('success', 'Availability updated successfully.');
    }

    public function destroy($id)
    {
        $user = auth()->user();

        if (! $user->caregiver) {
            return back()->with('error', 'You are not a caregiver.');
        }

        $availability = Availability::where('caregiver_id', $user->caregiver->id)
            ->findOrFail($id);

        $availability->delete();

        return back()->with('success', 'Availability deleted successfully.');
    }
}
