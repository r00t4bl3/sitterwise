<?php

namespace App\Http\Controllers;

use App\Enums\BookingPaymentStatus;
use App\Enums\BookingStatus;
use App\Enums\LocationType;
use App\Enums\ServiceType;
use App\Models\AttributeDefinition;
use App\Models\Booking;
use App\Models\BookingAddress;
use App\Models\BookingGroup;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\ClientAddress;
use App\Models\Hotel;
use App\Models\User;
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
            'bookingAddress',
            'attributeDefinitions',
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
            'special_consideration_options' => [
                ['value' => 'infant_care', 'label' => 'Infant Care'],
                ['value' => 'dogs_cats', 'label' => 'Dogs/Cats'],
                ['value' => 'pool', 'label' => 'Pool'],
            ],
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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required_without:new_client.first_name|nullable|exists:clients,id',
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
            'how_did_you_hear' => 'nullable|string',
            'sitter_preferences' => 'nullable|string',
            'other_adults_in_home' => 'nullable|string',
            'medical_info' => 'nullable|string',
            'emergency_instructions' => 'nullable|string',
            'comped' => 'nullable|boolean',
            'requires_payment' => 'nullable|boolean',
            'status' => 'required|string',
            'payment_status' => 'required|string',
            'vacation_rental_platform' => 'nullable|string',
            'booking_address.line1' => 'nullable|string',
            'booking_address.line2' => 'nullable|string',
            'booking_address.city' => 'nullable|string',
            'booking_address.state' => 'nullable|string',
            'booking_address.zip' => 'nullable|string',
            'new_client.first_name' => 'required_without:client_id|string',
            'new_client.last_name' => 'required_with:new_client.first_name|string',
            'new_client.email' => 'required_with:new_client.first_name|email|unique:users,email',
            'new_client.phone' => 'nullable|string',
            'new_client.client_type' => 'required_with:new_client.first_name|string',
        ]);

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
                'email' => $validated['new_client']['email'],
                'phone' => $validated['new_client']['phone'] ?? null,
                'client_type' => $validated['new_client']['client_type'] ?? 'individual',
                'corporate_id' => $validated['corporate_id'] ?? null,
                'how_did_you_hear' => $validated['how_did_you_hear'] ?? null,
                'sitter_preferences' => $validated['sitter_preferences'] ?? null,
                'other_adults_in_home' => $validated['other_adults_in_home'] ?? null,
                'medical_info' => $validated['medical_info'] ?? null,
                'emergency_instructions' => $validated['emergency_instructions'] ?? null,
                'caregiver_notes' => $validated['caregiver_notes'] ?? null,
            ]);

            $clientId = $client->id;

            // Create client address if private_home and new address provided
            if ($validated['location_type'] === 'private_home' &&
                ! empty($validated['booking_address']['line1'])) {
                ClientAddress::create([
                    'client_id' => $client->id,
                    'line1' => $validated['booking_address']['line1'],
                    'line2' => $validated['booking_address']['line2'] ?? null,
                    'city' => $validated['booking_address']['city'],
                    'state' => $validated['booking_address']['state'],
                    'zip' => $validated['booking_address']['zip'],
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
            'total_amount' => 0,
            'payment_status' => $validated['payment_status'],
            'requires_payment' => $validated['requires_payment'] ?? true,
        ]);

        // Save vacation rental platform attribute
        if (
            $validated['location_type'] === 'vacation_rental' &&
            ! empty($validated['vacation_rental_platform'])
        ) {
            $attribute = AttributeDefinition::where(
                'slug',
                'vacation_rental_platform'
            )->first();

            if ($attribute) {
                Booking::find($booking->id)
                    ->attributeDefinitions()
                    ->attach($attribute->id, [
                        'value' => $validated['vacation_rental_platform'],
                        'entity_type' => 'booking',
                    ]);
            }
        }

        // Save booking address for vacation rental
        if (
            $validated['location_type'] === 'vacation_rental' &&
            ! empty($validated['booking_address']['line1'])
        ) {
            BookingAddress::create([
                'booking_id' => $booking->id,
                'line1' => $validated['booking_address']['line1'],
                'line2' => $validated['booking_address']['line2'] ?? null,
                'city' => $validated['booking_address']['city'],
                'state' => $validated['booking_address']['state'],
                'zip' => $validated['booking_address']['zip'],
            ]);
        }

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
            'vacation_rental_platform' => 'nullable|string',
            'booking_address.line1' => 'nullable|string',
            'booking_address.line2' => 'nullable|string',
            'booking_address.city' => 'nullable|string',
            'booking_address.state' => 'nullable|string',
            'booking_address.zip' => 'nullable|string',
            'deleted_child_ids' => 'nullable|array',
            'deleted_child_ids.*' => 'integer|exists:client_children,id',
            'deleted_pet_ids' => 'nullable|array',
            'deleted_pet_ids.*' => 'integer|exists:client_pets,id',
            'new_children' => 'nullable|array',
            'new_pets' => 'nullable|array',
            'save_children_pets_to_profile' => 'nullable|boolean',
        ]);

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

        // Update vacation rental platform attribute
        if ($validated['location_type'] === 'vacation_rental') {
            $attribute = AttributeDefinition::where(
                'slug',
                'vacation_rental_platform'
            )->first();

            if ($attribute) {
                // Detach existing and attach new if provided
                $booking->attributeDefinitions()->detach($attribute->id);

                if (! empty($validated['vacation_rental_platform'])) {
                    $booking->attributeDefinitions()->attach($attribute->id, [
                        'value' => $validated['vacation_rental_platform'],
                        'entity_type' => 'booking',
                    ]);
                }
            }

            // Update or create booking address
            if (! empty($validated['booking_address']['line1'])) {
                BookingAddress::updateOrCreate(
                    ['booking_id' => $booking->id],
                    [
                        'line1' => $validated['booking_address']['line1'],
                        'line2' => $validated['booking_address']['line2'] ?? null,
                        'city' => $validated['booking_address']['city'],
                        'state' => $validated['booking_address']['state'],
                        'zip' => $validated['booking_address']['zip'],
                    ]
                );
            }
        } else {
            // If not vacation rental, delete associated data
            $attribute = AttributeDefinition::where(
                'slug',
                'vacation_rental_platform'
            )->first();

            if ($attribute) {
                $booking->attributeDefinitions()->detach($attribute->id);
            }

            $booking->bookingAddress()->delete();
        }

        return back()->with('success', 'Booking updated successfully.');
    }

    public function destroy(Booking $booking)
    {
        $booking->bookingGroup->delete();
        $booking->delete();

        return back()->with('success', 'Booking deleted successfully.');
    }
}
