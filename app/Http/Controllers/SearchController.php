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

        if (empty($query)) {
            return response()->json([]);
        }

        if ($user->isCaregiver()) {
            return $this->searchCaregiverBookings($query, $user);
        }

        if ($user->isClient()) {
            return $this->searchClientBookings($query, $user);
        }

        return $this->searchAdminAll($query);
    }

    private function searchCaregiverBookings(string $query, $user)
    {
        $caregiver = $user->caregiver;

        if (! $caregiver) {
            return response()->json([]);
        }

        $bookings = Booking::with('client')
            ->where('caregiver_id', $caregiver->id)
            ->where(function ($q) use ($query) {
                $q->where('ulid', 'like', "%{$query}%")
                    ->orWhere('corporate_id', 'like', "%{$query}%")
                    ->orWhere('address_line1', 'like', "%{$query}%")
                    ->orWhere('address_city', 'like', "%{$query}%")
                    ->orWhere('address_state', 'like', "%{$query}%")
                    ->orWhere('address_zip', 'like', "%{$query}%")
                    ->orWhereHas('client', function ($cq) use ($query) {
                        $cq->where('first_name', 'like', "%{$query}%")
                            ->orWhere('last_name', 'like', "%{$query}%");
                    })
                    ->orWhereHas('hotel', function ($hq) use ($query) {
                        $hq->where('name', 'like', "%{$query}%");
                    });
            })
            ->limit(5)
            ->get()
            ->map(function ($booking) {
                $clientName = $booking->client
                    ? $booking->client->first_name.' '.$booking->client->last_name
                    : 'Unknown Client';

                return [
                    'id' => $booking->id,
                    'name' => $clientName,
                    'subtitle' => ($booking->service_type_label ?? $booking->service_type).' — '.$booking->start_datetime->format('D, M j, Y'),
                    'type' => 'booking',
                    'url' => route('jobs.show', $booking),
                    'ulid' => $booking->ulid,
                    'corporate_id' => $booking->corporate_id,
                ];
            });

        return response()->json($bookings);
    }

    private function searchClientBookings(string $query, $user)
    {
        $client = $user->client;

        if (! $client) {
            return response()->json([]);
        }

        $bookings = Booking::with('caregiver')
            ->where('client_id', $client->id)
            ->where(function ($q) use ($query) {
                $q->where('ulid', 'like', "%{$query}%")
                    ->orWhere('corporate_id', 'like', "%{$query}%")
                    ->orWhere('address_line1', 'like', "%{$query}%")
                    ->orWhere('address_city', 'like', "%{$query}%")
                    ->orWhere('address_state', 'like', "%{$query}%")
                    ->orWhere('address_zip', 'like', "%{$query}%")
                    ->orWhereHas('caregiver', function ($cq) use ($query) {
                        $cq->where('first_name', 'like', "%{$query}%")
                            ->orWhere('last_name', 'like', "%{$query}%");
                    })
                    ->orWhereHas('hotel', function ($hq) use ($query) {
                        $hq->where('name', 'like', "%{$query}%");
                    });
            })
            ->limit(5)
            ->get()
            ->map(function ($booking) {
                $caregiverName = $booking->caregiver
                    ? $booking->caregiver->first_name.' '.$booking->caregiver->last_name
                    : 'Unassigned';

                return [
                    'id' => $booking->id,
                    'name' => $caregiverName,
                    'subtitle' => ($booking->service_type_label ?? $booking->service_type).' — '.$booking->start_datetime->format('D, M j, Y'),
                    'type' => 'booking',
                    'url' => route('bookings.show', $booking),
                    'ulid' => $booking->ulid,
                    'corporate_id' => $booking->corporate_id,
                ];
            });

        return response()->json($bookings);
    }

    private function searchAdminAll(string $query)
    {
        $terms = array_filter(explode(' ', $query));

        // Priority 1: Bookings
        $bookings = Booking::with(['client', 'caregiver'])
            ->where(function ($q) use ($query) {
                $q->where('ulid', 'like', "%{$query}%")
                    ->orWhere('corporate_id', 'like', "%{$query}%")
                    ->orWhere('address_line1', 'like', "%{$query}%")
                    ->orWhere('address_city', 'like', "%{$query}%")
                    ->orWhere('address_state', 'like', "%{$query}%")
                    ->orWhere('address_zip', 'like', "%{$query}%")
                    ->orWhereHas('client', function ($cq) use ($query) {
                        $cq->where('first_name', 'like', "%{$query}%")
                            ->orWhere('last_name', 'like', "%{$query}%");
                    })
                    ->orWhereHas('caregiver', function ($cq) use ($query) {
                        $cq->where('first_name', 'like', "%{$query}%")
                            ->orWhere('last_name', 'like', "%{$query}%");
                    })
                    ->orWhereHas('hotel', function ($hq) use ($query) {
                        $hq->where('name', 'like', "%{$query}%");
                    });
            })
            ->limit(5)
            ->get()
            ->map(function ($booking) {
                $clientName = $booking->client
                    ? $booking->client->first_name.' '.$booking->client->last_name
                    : 'Unknown Client';

                return [
                    'id' => $booking->id,
                    'name' => $booking->corporate_id ?? $booking->ulid,
                    'subtitle' => $clientName.' — '.($booking->service_type_label ?? $booking->service_type).' — '.$booking->start_datetime->format('D, M j, Y'),
                    'type' => 'booking',
                    'url' => route('bookings.show', $booking),
                    'ulid' => $booking->ulid,
                    'corporate_id' => $booking->corporate_id,
                ];
            });

        // Priority 2: Caregivers
        $caregivers = Caregiver::where(function ($q) use ($terms) {
            foreach ($terms as $term) {
                $q->where(function ($q) use ($term) {
                    $q->where('first_name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%");
                });
            }
        })
            ->limit(5)
            ->get(['id', 'first_name', 'last_name'])
            ->map(fn ($caregiver) => [
                'id' => $caregiver->id,
                'name' => "{$caregiver->first_name} {$caregiver->last_name}",
                'type' => 'caregiver',
                'url' => route('caregivers.show', $caregiver),
            ]);

        // Priority 3: Clients
        $clients = Client::where(function ($q) use ($terms) {
            foreach ($terms as $term) {
                $q->where(function ($q) use ($term) {
                    $q->where('first_name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%");
                });
            }
        })
            ->limit(5)
            ->get(['id', 'first_name', 'last_name'])
            ->map(fn ($client) => [
                'id' => $client->id,
                'name' => "{$client->first_name} {$client->last_name}",
                'type' => 'client',
                'url' => route('clients.show', $client),
            ]);

        $results = array_merge(
            $bookings->toArray(),
            $caregivers->toArray(),
            $clients->toArray()
        );

        return response()->json($results);
    }
}
