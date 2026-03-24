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
        $user = Auth::user()->load('caregiver.status');

        $stats = [];

        if ($user->isAdmin()) {
            $stats = [
                'total_caregivers' => Caregiver::count(),
                'active_caregivers' => Caregiver::whereHas('status', function ($query) {
                    $query->where('name', 'Active');
                })->count(),
                'total_clients' => User::where('role', 'client')->count(),
            ];
        }

        return Inertia::render('dashboard', [
            'auth' => [
                'user' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'caregiver' => $user->caregiver ? [
                        'first_name' => $user->caregiver->first_name,
                        'last_name' => $user->caregiver->last_name,
                        'rating' => $user->caregiver->rating,
                        'status' => $user->caregiver->status ? [
                            'name' => $user->caregiver->status->name,
                        ] : null,
                    ] : null,
                ],
            ],
            'stats' => $stats,
        ]);
    }
}
