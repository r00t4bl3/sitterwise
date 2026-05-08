<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Caregiver;
use App\Models\Client;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $query = $request->input('q', '');
        $user = $request->user();
        $results = collect();

        if (empty($query)) {
            return response()->json($results);
        }

        // Priority 1: Bookings (corporate_id, ulid)
        $bookingQuery = Booking::where(function ($q) use ($query) {
            $q->where('corporate_id', 'like', "%{$query}%")
                ->orWhere('ulid', 'like', "%{$query}%");
        });

        // Filter bookings based on user role
        if ($user->isCaregiver()) {
            $caregiver = $user->caregiver;
            if ($caregiver) {
                $bookingQuery->where('caregiver_id', $caregiver->id);
            }
        } elseif ($user->isClient()) {
            $client = $user->client;
            if ($client) {
                $bookingQuery->where('client_id', $client->id);
            }
        }

        $bookings = $bookingQuery
            ->limit(5)
            ->get(['id', 'ulid', 'corporate_id'])
            ->map(fn ($booking) => [
                'id' => $booking->id,
                'name' => $booking->corporate_id ?? $booking->ulid,
                'type' => 'booking',
                'url' => route('bookings.show', $booking),
                'ulid' => $booking->ulid,
                'corporate_id' => $booking->corporate_id,
            ]);

        // Only admin/superadmin can search caregivers and clients
        if ($user->isAdmin() || $user->isSuperAdmin()) {
            // Priority 2: Caregivers (names)
            $caregivers = Caregiver::where('first_name', 'like', "%{$query}%")
                ->orWhere('last_name', 'like', "%{$query}%")
                ->limit(5)
                ->get(['id', 'first_name', 'last_name'])
                ->map(fn ($caregiver) => [
                    'id' => $caregiver->id,
                    'name' => "{$caregiver->first_name} {$caregiver->last_name}",
                    'type' => 'caregiver',
                    'url' => route('caregivers.index', $caregiver),
                ]);

            // Priority 3: Clients (names)
            $clients = Client::where('first_name', 'like', "%{$query}%")
                ->orWhere('last_name', 'like', "%{$query}%")
                ->limit(5)
                ->get(['id', 'first_name', 'last_name'])
                ->map(fn ($client) => [
                    'id' => $client->id,
                    'name' => "{$client->first_name} {$client->last_name}",
                    'type' => 'client',
                    'url' => route('clients.index', $client),
                ]);
        } else {
            $caregivers = collect();
            $clients = collect();
        }

        // Combine results in priority order
        $results = array_merge(
            $bookings->toArray(),
            $caregivers->toArray(),
            $clients->toArray()
        );

        return response()->json($results);
    }
}
