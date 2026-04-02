<?php

namespace App\Http\Controllers;

use App\Models\Caregiver;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $stats = [];
        $caregiverData = null;

        if ($user->isAdmin()) {
            $stats = [
                'total_caregivers' => Caregiver::count(),
                'active_caregivers' => Caregiver::whereHas('status', function ($query) {
                    $query->where('name', 'Active');
                })->count(),
                'total_clients' => User::where('role', 'client')->count(),
            ];
        }

        if ($user->isCaregiver()) {
            $user->load(['caregiver.status']);
            $availabilities = $user->caregiver?->availabilities()
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

            $caregiverData = $user->caregiver ? [
                'id' => $user->caregiver->id,
                'first_name' => $user->caregiver->first_name,
                'last_name' => $user->caregiver->last_name,
                'rating' => $user->caregiver->rating,
                'status' => $user->caregiver->status ? [
                    'name' => $user->caregiver->status->name,
                ] : null,
                'availabilities' => $availabilities,
            ] : null;
        }

        return Inertia::render('dashboard', [
            'user' => [
                'name' => $user->name,
                'role' => $user->role,
            ],
            'stats' => $stats,
            'caregiver' => $caregiverData,
        ]);
    }
}
