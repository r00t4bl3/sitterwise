<?php
namespace App\Http\Controllers;

use App\Enums\TimeSlot;
use App\Models\Availability;
use App\Models\Caregiver;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AvailabilityController extends Controller
{
    public function index(Request $request)
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
                    $q->where('date', '>=', now()->toDateString())->orderBy('date');
                },
            ]);

        return Inertia::render('availabilities/index', [
            'caregivers' => Inertia::scroll(fn() => $caregivers->paginate($perPage)),
            'timeSlots'  => array_map(
                fn($case) => ['value' => $case->value, 'label' => $case->label()],
                TimeSlot::cases()
            ),
        ]);
    }

    public function store(Request $request)
    {
        // $validated = $request->validate([
        //     'caregiver_id'  => 'required|exists:caregivers,id',
        //     'date'          => 'required|date|after_or_equal:today',
        //     'time_slots'    => 'required|array|min:1',
        //     'time_slots.*'  => 'required|in:morning,afternoon,evening',
        //     'specific_time' => 'nullable|string|max:255',
        // ]);

        // $availability = Availability::updateOrCreate(
        //     [
        //         'caregiver_id' => $validated['caregiver_id'],
        //         'date'         => $validated['date'],
        //     ],
        //     [
        //         'time_slots'    => $validated['time_slots'],
        //         'specific_time' => $validated['specific_time'] ?? null,
        //     ]
        // );

        // return back()->with('success', 'Availability saved successfully.');
    }

    public function update(Request $request, Availability $availability)
    {
        // $validated = $request->validate([
        //     'date'          => 'required|date|after_or_equal:today',
        //     'time_slots'    => 'required|array|min:1',
        //     'time_slots.*'  => 'required|in:morning,afternoon,evening',
        //     'specific_time' => 'nullable|string|max:255',
        // ]);

        // $availability->update([
        //     'date'          => $validated['date'],
        //     'time_slots'    => $validated['time_slots'],
        //     'specific_time' => $validated['specific_time'] ?? null,
        // ]);

        // return back()->with('success', 'Availability updated successfully.');
    }

    public function destroy(Availability $availability)
    {
        // $availability->delete();

        // return back()->with('success', 'Availability deleted successfully.');
    }

    public function myAvailability(Request $request)
    {
        $user = $request->user();

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
            'timeSlots'      => array_map(
                fn($case) => ['value' => $case->value, 'label' => $case->label()],
                TimeSlot::cases()
            ),
        ]);
    }

    public function updateMyAvailability(Request $request)
    {
        $user = $request->user();

        if (! $user->caregiver) {
            return redirect('/dashboard')->with('error', 'You are not a caregiver.');
        }

        $validated = $request->validate([
            'availabilities'                 => 'required|array',
            'availabilities.*.date'          => 'required|date|after_or_equal:today',
            'availabilities.*.time_slots'    => 'required|array',
            'availabilities.*.time_slots.*'  => 'in:morning,afternoon,evening',
            'availabilities.*.specific_time' => 'nullable|string|max:255',
        ]);

        foreach ($validated['availabilities'] as $item) {
            if (empty($item['time_slots'])) {
                Availability::where('caregiver_id', $user->caregiver->id)
                    ->where('date', $item['date'])
                    ->delete();
            } else {
                Availability::updateOrCreate(
                    [
                        'caregiver_id' => $user->caregiver->id,
                        'date'         => $item['date'],
                    ],
                    [
                        'time_slots'    => array_values($item['time_slots']),
                        'specific_time' => $item['specific_time'] ?? null,
                    ]
                );
            }
        }

        return back()->with('success', 'Availability updated successfully.');
    }

    public function manage(Caregiver $caregiver)
    {
        $availabilities = $caregiver->availabilities()
            ->where('date', '>=', now()->toDateString())
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

        return Inertia::render('caregivers/availability/manage', [
            'caregiver'      => $caregiver->load(['user', 'status', 'locations', 'specialtyTypes']),
            'availabilities' => $availabilities,
            'timeSlots'      => array_map(
                fn($case) => ['value' => $case->value, 'label' => $case->label()],
                TimeSlot::cases()
            ),
        ]);
    }

    public function storeForCaregiver(Request $request, Caregiver $caregiver)
    {
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

    public function destroyForCaregiver(Request $request, Caregiver $caregiver, Availability $availability)
    {
        $availability->delete();

        return back()->with('success', 'Availability deleted successfully.');
    }
}