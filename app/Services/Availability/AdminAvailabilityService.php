<?php
namespace App\Services\Availability;

use App\Enums\TimeSlot;
use App\Models\Availability;
use App\Models\Caregiver;
use App\Services\Availability\Contracts\AvailabilityServiceInterface;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminAvailabilityService implements AvailabilityServiceInterface
{
    public function index()
    {
        $perPage = 20;

        $caregiverIds = Availability::where('date', '>=', now()->toDateString())
            ->distinct()
            ->pluck('caregiver_id');

        $caregivers = Caregiver::whereIn('id', $caregiverIds)
            ->with([
                'user',
                'status',
                'locations',
                'specialtyTypes',
                'certifications',
                'availabilities' => function ($q) {
                    $q->inTheFuture()->orderBy('date');
                },
            ]);

        return Inertia::render('admin/availabilities/index', [
            'caregivers' => Inertia::scroll(fn() => $caregivers->paginate($perPage)),
            'timeSlots'  => array_map(
                fn($case) => ['value' => $case->value, 'label' => $case->label()],
                TimeSlot::cases()
            ),
        ]);
    }

    public function update(Request $request, $id)
    {
        $caregiver = Caregiver::findOrFail($id);
        $validated = $request->validate([
            'date'          => 'required|date|after_or_equal:today',
            'time_slots'    => 'required|array|min:1',
            'time_slots.*'  => 'required|in:morning,afternoon,evening',
            'specific_time' => 'nullable|string|max:255',
        ]);

        Availability::updateOrCreate(
            [
                'caregiver_id' => $caregiver->id,
                'date'         => Carbon::parse($validated['date'])->toDateTimeString(),
            ],
            [
                'time_slots'    => $validated['time_slots'],
                'specific_time' => $validated['specific_time'] ?? null,
            ]
        );

        return back()->with('success', 'Availability saved successfully.');
    }

    public function destroy($id)
    {
        $availability = Availability::findOrFail($id);
        $availability->delete();

        return back()->with('success', 'Availability deleted successfully.');
    }

    public function show($id)
    {
        $caregiver      = Caregiver::findOrFail($id);
        $availabilities = $caregiver->availabilities()
            ->inTheFuture()
            ->orderBy('date')
            ->get()
            ->map(function ($availability) {
                return [
                    'id'            => $availability->id,
                    'date'          => $availability->date->format('Y-m-d'),
                    'time_slots'    => $availability->time_slots,
                    'specific_time' => $availability->specific_time,
                ];
            });

        return Inertia::render('admin/availabilities/show', [
            'caregiver'      => $caregiver->load(['user', 'status', 'locations', 'specialtyTypes']),
            'availabilities' => $availabilities,
            'timeSlots'      => array_map(
                fn($case) => ['value' => $case->value, 'label' => $case->label()],
                TimeSlot::cases()
            ),
        ]);
    }
}