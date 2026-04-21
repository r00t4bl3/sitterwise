<?php

namespace App\Services\Availability;

use App\Models\Availability;
use App\Models\Caregiver;
use App\Services\Availability\Contracts\AvailabilityServiceInterface;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CaregiverAvailabilityService implements AvailabilityServiceInterface
{
    public function index()
    {
        // TODO: Implement index page
    }

    public function show($id)
    {
        // TODO: Implement show page
    }

    public function update(Request $request, $id)
    {
        $caregiver = Caregiver::findOrFail($id);
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
                'caregiver_id' => $caregiver->id,
                'date' => Carbon::parse($validated['date'])->toDateTimeString(),
            ],
            [
                'time_slots' => $validated['time_slots'],
                'specific_time' => $validated['specific_time'] ?? null,
            ]
        );

        return back()->with('success', 'Availability updated successfully.');
    }

    public function destroy($id)
    {
        $user = auth()->user();
        $caregiver = $user->caregiver;

        if (! $user->caregiver) {
            return back()->with('error', 'You are not a caregiver.');
        }

        $availability = Availability::where('caregiver_id', $caregiver->id)
            ->findOrFail($id);

        $availability->delete();

        return back()->with('success', 'Availability deleted successfully.');
    }
}
