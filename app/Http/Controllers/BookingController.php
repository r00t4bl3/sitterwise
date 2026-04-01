<?php

namespace App\Http\Controllers;

use App\Enums\BookingPaymentStatus;
use App\Enums\BookingStatus;
use App\Enums\LocationType;
use App\Enums\ServiceType;
use App\Models\Booking;
use App\Models\BookingGroup;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\Hotel;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BookingController extends Controller
{
    public function searchHotels(Request $request)
    {
        $query = $request->input('q', '');

        $hotels = Hotel::where('is_active', true)
            ->where('name', 'like', "%{$query}%")
            ->limit(10)
            ->get(['id', 'name', 'city'])
            ->map(fn ($h) => [
                'id' => $h->id,
                'name' => $h->name.($h->city ? ", {$h->city}" : ''),
            ]);

        return response()->json($hotels);
    }

    public function index(Request $request)
    {
        $month = (int) $request->input('month', now()->month);
        $year = (int) $request->input('year', now()->year);
        $status = $request->input('status');

        $startDate = now()->year($year)->month($month)->startOfMonth();
        $endDate = $startDate->endOfMonth();

        $query = Booking::with([
            'client.user',
            'hotel',
            'caregiver.user',
            'address',
        ])
            ->whereBetween('start_datetime', [$startDate, $endDate])
            ->orderBy('start_datetime');

        if ($status) {
            $query->where('status', $status);
        }

        $bookings = $query->get();

        $clients = Client::all()->map(fn ($c) => [
            'id' => $c->id,
            'name' => $c->first_name.' '.$c->last_name,
            'email' => $c->email,
        ]);

        $hotels = Hotel::where('is_active', true)->get()->map(fn ($h) => [
            'id' => $h->id,
            'name' => $h->name,
            'city' => $h->city,
        ]);

        $caregivers = Caregiver::with('user')->get()->map(fn ($c) => [
            'id' => $c->id,
            'name' => $c->first_name.' '.$c->last_name,
        ]);

        return Inertia::render('admin/bookings/index', [
            'bookings' => $bookings,
            'filters' => [
                'month' => (int) $month,
                'year' => (int) $year,
                'status' => $status,
            ],
            'clients' => $clients,
            'hotels' => $hotels,
            'caregivers' => $caregivers,
            'service_types' => array_map(
                fn ($case) => ['value' => $case->value, 'label' => $case->label()],
                ServiceType::cases()
            ),
            'location_types' => array_map(
                fn ($case) => ['value' => $case->value, 'label' => $case->label()],
                LocationType::cases()
            ),
            'booking_statuses' => array_map(
                fn ($case) => ['value' => $case->value, 'label' => $case->label()],
                BookingStatus::cases()
            ),
            'payment_statuses' => array_map(
                fn ($case) => ['value' => $case->value, 'label' => $case->label()],
                BookingPaymentStatus::cases()
            ),
            'special_consideration_options' => [
                ['value' => 'infant_care', 'label' => 'Infant Care'],
                ['value' => 'dogs_cats', 'label' => 'Dogs/Cats'],
                ['value' => 'pool', 'label' => 'Pool'],
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'service_type' => 'required|string',
            'location_type' => 'required|string',
            'start_datetime' => 'required|date',
            'end_datetime' => 'required|date|after:start_datetime',
            'hotel_id' => 'nullable|exists:hotels,id',
            'address_id' => 'nullable|exists:client_addresses,id',
            'caregiver_id' => 'nullable|exists:caregivers,id',
            'special_considerations' => 'nullable|array',
            'caregiver_notes' => 'nullable|string',
            'notes_to_sitterwise' => 'nullable|string',
            'admin_notes' => 'nullable|string',
            'corporate_id' => 'nullable|string',
            'comped' => 'nullable|boolean',
            'total_amount' => 'required|numeric|min:0',
            'requires_payment' => 'nullable|boolean',
            'status' => 'required|string',
            'payment_status' => 'required|string',
        ]);

        $bookingGroup = BookingGroup::create([
            'client_id' => $validated['client_id'],
            'submitted_at' => now(),
            'submission_type' => 'admin',
            'is_split' => false,
        ]);

        $booking = Booking::create([
            'booking_group_id' => $bookingGroup->id,
            'client_id' => $validated['client_id'],
            'caregiver_id' => $validated['caregiver_id'] ?? null,
            'availability_id' => null,
            'hotel_id' => $validated['hotel_id'] ?? null,
            'address_id' => $validated['address_id'] ?? null,
            'service_type' => $validated['service_type'],
            'location_type' => $validated['location_type'],
            'start_datetime' => $validated['start_datetime'],
            'end_datetime' => $validated['end_datetime'],
            'status' => $validated['status'],
            'special_considerations' => $validated['special_considerations'] ?? null,
            'caregiver_notes' => $validated['caregiver_notes'] ?? null,
            'notes_to_sitterwise' => $validated['notes_to_sitterwise'] ?? null,
            'admin_notes' => $validated['admin_notes'] ?? null,
            'corporate_id' => $validated['corporate_id'] ?? null,
            'comped' => $validated['comped'] ?? false,
            'total_amount' => $validated['total_amount'],
            'payment_status' => $validated['payment_status'],
            'requires_payment' => $validated['requires_payment'] ?? true,
        ]);

        return back()->with('success', 'Booking created successfully.');
    }

    public function update(Request $request, Booking $booking)
    {
        $validated = $request->validate([
            'service_type' => 'required|string',
            'location_type' => 'required|string',
            'start_datetime' => 'required|date',
            'end_datetime' => 'required|date|after:start_datetime',
            'hotel_id' => 'nullable|exists:hotels,id',
            'address_id' => 'nullable|exists:client_addresses,id',
            'caregiver_id' => 'nullable|exists:caregivers,id',
            'special_considerations' => 'nullable|array',
            'caregiver_notes' => 'nullable|string',
            'notes_to_sitterwise' => 'nullable|string',
            'admin_notes' => 'nullable|string',
            'corporate_id' => 'nullable|string',
            'comped' => 'nullable|boolean',
            'total_amount' => 'required|numeric|min:0',
            'requires_payment' => 'nullable|boolean',
            'status' => 'required|string',
            'payment_status' => 'required|string',
        ]);

        $booking->update($validated);

        return back()->with('success', 'Booking updated successfully.');
    }

    public function destroy(Booking $booking)
    {
        $booking->bookingGroup->delete();
        $booking->delete();

        return back()->with('success', 'Booking deleted successfully.');
    }
}
