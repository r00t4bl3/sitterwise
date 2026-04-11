<?php

namespace App\Http\Controllers;

use App\Enums\BookingPaymentStatus;
use App\Enums\BookingStatus;
use App\Enums\LocationType;
use App\Enums\ServiceType;
use App\Enums\SpecialConsideration;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Requests\UpdateBookingRequest;
use App\Models\AttributeDefinition;
use App\Models\Booking;
use App\Models\BookingGroup;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\ClientAddress;
use App\Models\ClientChild;
use App\Models\ClientPet;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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
            'attributeDefinitions',
        ])
            ->whereBetween('start_datetime', [$startDate, $endDate])
            ->orderBy('start_datetime');

        if ($status) {
            $query->where('status', $status);
        }

        $bookings = $query->get();

        $clients = Client::with('user')->get()->map(fn ($c) => [
            'id' => $c->id,
            'name' => $c->first_name.' '.$c->last_name,
            'email' => $c->user->email,
        ]);

        $hotels = Hotel::where('is_active', true)->get()->map(fn ($h) => [
            'id' => $h->id,
            'name' => $h->name,
            'city' => $h->city,
            'line1' => $h->line1,
            'line2' => $h->line2,
            'state' => $h->state,
            'zip' => $h->zip,
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
            // TODO: Move special_consideration_options to booking_attributes table
            // and derive dynamically once the 'special_considerations' attribute definition exists.
            'special_consideration_options' => array_map(
                fn ($case) => ['value' => $case->value, 'label' => $case->label()],
                SpecialConsideration::cases(),
            ),
            'booking_attributes' => AttributeDefinition::active()
                ->forBookings()
                ->get()
                ->map(fn ($attr) => [
                    'id' => $attr->id,
                    'name' => $attr->name,
                    'slug' => $attr->slug,
                    'type' => $attr->type,
                    'options' => $attr->options,
                ]),
        ]);
    }

    public function store(StoreBookingRequest $request)
    {
        $validated = $request->validated();

        // Validate minimum 4-hour duration
        $start = new \DateTime($validated['start_datetime']);
        $end = new \DateTime($validated['end_datetime']);
        $diffHours = $start->diff($end)->h + ($start->diff($end)->days * 24);
        if ($diffHours < 4) {
            throw ValidationException::withMessages([
                'end_datetime' => 'Booking must be at least 4 hours long.',
            ]);
        }

        $clientId = $validated['client_id'] ?? null;

        // Create new client if new_client data provided
        if (! empty($validated['new_client']['first_name'])) {
            $user = User::create([
                'name' => $validated['new_client']['first_name'].' '.$validated['new_client']['last_name'],
                'email' => $validated['new_client']['email'],
                'password' => \Hash::make(\Str::random(16)),
                'role' => 'client',
            ]);

            $client = Client::create([
                'user_id' => $user->id,
                'first_name' => $validated['new_client']['first_name'],
                'last_name' => $validated['new_client']['last_name'],
                'phone' => $validated['new_client']['phone'] ?? null,
                'client_type' => $validated['new_client']['client_type'] ?? 'vacationer',
                'corporate_id' => $validated['corporate_id'] ?? null,
                'how_did_you_hear' => $validated['how_did_you_hear'] ?? null,
                'sitter_preferences' => $validated['sitter_preferences'] ?? null,
                'other_adults_present' => $validated['other_adults_present'] ?? null,
                'emergency_instructions' => $validated['emergency_instructions'] ?? null,
            ]);

            $clientId = $client->id;

            // Create client address if private_home and new address provided
            if ($validated['location_type'] === 'private_home' &&
                ! empty($validated['address_line1'])) {
                ClientAddress::create([
                    'client_id' => $client->id,
                    'line1' => $validated['address_line1'],
                    'line2' => $validated['address_line2'] ?? null,
                    'city' => $validated['address_city'],
                    'state' => $validated['address_state'],
                    'zip' => $validated['address_zip'],
                ]);
            }
        }

        $bookingGroup = BookingGroup::create([
            'client_id' => $clientId,
            'submitted_at' => now(),
            'submission_type' => 'admin',
            'is_split' => false,
        ]);

        $booking = Booking::create([
            'booking_group_id' => $bookingGroup->id,
            'client_id' => $clientId,
            'caregiver_id' => $validated['caregiver_id'] ?? null,
            'availability_id' => null,
            'hotel_id' => $validated['hotel_id'] ?? null,
            'address_id' => $validated['address_id'] ?? null,
            'address_line1' => $validated['address_line1'] ?? null,
            'address_line2' => $validated['address_line2'] ?? null,
            'address_city' => $validated['address_city'] ?? null,
            'address_state' => $validated['address_state'] ?? null,
            'address_zip' => $validated['address_zip'] ?? null,
            'service_type' => $validated['service_type'],
            'location_type' => $validated['location_type'],
            'rental_platform' => $validated['rental_platform'] ?? null,
            'start_datetime' => $validated['start_datetime'],
            'end_datetime' => $validated['end_datetime'],
            'status' => $validated['status'],
            'special_considerations' => $validated['special_considerations'] ?? null,
            'caregiver_notes' => $validated['caregiver_notes'] ?? null,
            'notes_to_sitterwise' => $validated['notes_to_sitterwise'] ?? null,
            'admin_notes' => $validated['admin_notes'] ?? null,
            'corporate_id' => $validated['corporate_id'] ?? null,
            'sitter_preferences' => $validated['sitter_preferences'] ?? null,
            'other_adults_present' => $validated['other_adults_present'] ?? null,
            'special_needs_notes' => $validated['special_needs_notes'] ?? null,
            'emergency_instructions' => $validated['emergency_instructions'] ?? null,
            'total_amount' => 0,
            'payment_status' => $validated['payment_status'],
            'requires_payment' => $validated['requires_payment'] ?? true,
        ]);

        return redirect()->route('bookings.index')->with('success', 'Booking created successfully.');
    }

    public function update(UpdateBookingRequest $request, Booking $booking)
    {
        $validated = $request->validated();

        // Validate minimum 4-hour duration
        $start = new \DateTime($validated['start_datetime']);
        $end = new \DateTime($validated['end_datetime']);
        $diffHours = $start->diff($end)->h + ($start->diff($end)->days * 24);
        if ($diffHours < 4) {
            throw ValidationException::withMessages([
                'end_datetime' => 'Booking must be at least 4 hours long.',
            ]);
        }

        $booking->update($validated);

        // Handle deleted children
        if (! empty($validated['deleted_child_ids'])) {
            ClientChild::whereIn('id', $validated['deleted_child_ids'])->delete();
        }

        // Handle deleted pets
        if (! empty($validated['deleted_pet_ids'])) {
            ClientPet::whereIn('id', $validated['deleted_pet_ids'])->delete();
        }

        // Handle new children - save to client profile
        if (
            ! empty($validated['new_children']) &&
            $validated['save_children_pets_to_profile'] &&
            $booking->client_id
        ) {
            foreach ($validated['new_children'] as $childData) {
                ClientChild::create([
                    'client_id' => $booking->client_id,
                    'name' => $childData['name'] ?? null,
                    'gender' => $childData['gender'] ?? null,
                    'birth_month' => $childData['birth_month'] ? (int) $childData['birth_month'] : null,
                    'birth_year' => $childData['birth_year'] ? (int) $childData['birth_year'] : null,
                ]);
            }
        }

        // Handle new pets - save to client profile
        if (
            ! empty($validated['new_pets']) &&
            $validated['save_children_pets_to_profile'] &&
            $booking->client_id
        ) {
            foreach ($validated['new_pets'] as $petData) {
                ClientPet::create([
                    'client_id' => $booking->client_id,
                    'name' => $petData['name'] ?? null,
                    'type' => $petData['type'] ?? null,
                    'breed' => $petData['breed'] ?? null,
                    'notes' => $petData['notes'] ?? null,
                ]);
            }
        }

        return redirect()->route('bookings.index')->with('success', 'Booking updated successfully.');
    }

    public function destroy(Booking $booking)
    {
        $booking->bookingGroup->delete();
        $booking->delete();

        return redirect()->route('bookings.index')->with('success', 'Booking deleted successfully.');
    }

    public function notify(Request $request, Booking $booking)
    {
        $validated = $request->validate([
            'caregiver_ids' => 'required|array',
            'caregiver_ids.*' => 'exists:caregivers,id',
        ]);

        $caregivers = Caregiver::whereIn('id', $validated['caregiver_ids'])->get();

        // TODO: Send notifications to caregivers (email, SMS, push, etc.)
        // For now, we'll just return a success response
        // You can implement the notification logic here or dispatch a job

        return back()->with('success', 'Caregivers have been notified.');
    }
}
