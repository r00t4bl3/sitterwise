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

        $caregiverIds = Availability::where('date', '>=', now('America/Los_Angeles')->toDateString())
            ->distinct()
            ->pluck('caregiver_id');

        $caregivers = Caregiver::whereIn('id', $caregiverIds)
            ->with([
                'user',
                'locations',
                'specialtyTypes',
                'certifications',
                'availabilities' => function ($q) {
                    $q->with('usedSlots')->inTheFuture()->orderBy('date');
                },
            ]);

        return Inertia::render('admin/availabilities/index', [
            'caregivers' => Inertia::scroll(fn () => $caregivers->paginate($perPage)),
            'timeSlots' => array_map(
                fn ($case) => ['value' => $case->value, 'label' => $case->label()],
                TimeSlot::cases()
            ),
        ]);
    }

    public function update(Request $request, $id)
    {
        $caregiver = Caregiver::findOrFail($id);
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

    public function destroy(Availability $availability)
    {
        $availability->delete();

        return back()->with('success', 'Availability deleted successfully.');
    }

    public function show($id)
    {
        $caregiver = Caregiver::findOrFail($id);
        $availabilities = $caregiver->availabilities()
            ->with('usedSlots')
            ->inTheFuture()
            ->orderBy('date')
            ->limit(32)
            ->get()
            ->map(function ($availability) {
                return [
                    'id' => $availability->id,
                    'date' => $availability->date instanceof Carbon
                        ? $availability->date->format('Y-m-d')
                        : (is_string($availability->date) ? $availability->date : $availability->date->format('Y-m-d')),
                    'time_slots' => $availability->time_slots,
                    'specific_time' => $availability->specific_time,
                    'booked_slots' => $availability->usedSlots->pluck('time_slot')->toArray(),
                ];
            });

        return Inertia::render('admin/availabilities/show', [
            'caregiver' => $caregiver->load(['user', 'locations', 'specialtyTypes']),
            'availabilities' => $availabilities,
            'timeSlots' => array_map(
                fn ($case) => ['value' => $case->value, 'label' => $case->label()],
                TimeSlot::cases()
            ),
        ]);
    }

    public function store(Request $request, string $caregiverId)
    {
        $caregiver = Caregiver::findOrFail($caregiverId);

        $validated = $request->validate([
            'days' => ['required', 'array', 'max:7'],
            'days.*.date' => ['required', 'date_format:Y-m-d'],
            'days.*.time_slots' => ['present', 'array'],
            'days.*.time_slots.*' => ['in:morning,afternoon,evening'],
        ]);

        foreach ($validated['days'] as $day) {
            $timeSlots = $day['time_slots'];

            if (empty($timeSlots)) {
                $caregiver->availabilities()
                    ->whereDate('date', $day['date'])
                    ->delete();

                continue;
            }

            Availability::updateOrCreate(
                [
                    'caregiver_id' => $caregiver->id,
                    'date' => $day['date'],
                ],
                [
                    'time_slots' => $timeSlots,
                    'specific_time' => null,
                ]
            );
        }

        return back()->with('success', 'Availability saved successfully.');
    }

    public function getMonth(int $year, int $month, string $caregiverId)
    {
        $caregiver = Caregiver::findOrFail($caregiverId);

        $startDate = Carbon::create($year, $month, 1)->startOfMonth()->startOfWeek(Carbon::SUNDAY);
        $endDate = Carbon::create($year, $month, 1)->endOfMonth()->endOfWeek(Carbon::SATURDAY);

        $availabilities = $caregiver->availabilities()
            ->with('usedSlots')
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->orderBy('date')
            ->get()
            ->map(function ($availability) {
                return [
                    'id' => $availability->id,
                    'date' => $availability->date instanceof Carbon
                        ? $availability->date->format('Y-m-d')
                        : (is_string($availability->date) ? $availability->date : $availability->date->format('Y-m-d')),
                    'time_slots' => $availability->time_slots,
                    'specific_time' => $availability->specific_time,
                    'booked_slots' => $availability->usedSlots->pluck('time_slot')->toArray(),
                ];
            });

        return response()->json([
            'availabilities' => $availabilities,
            'timeSlots' => array_map(
                fn ($case) => ['value' => $case->value, 'label' => $case->label()],
                TimeSlot::cases()
            ),
        ]);
    }
}
