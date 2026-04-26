<?php

namespace App\Services\Booking;

use App\Enums\LocationType;
use App\Enums\ServiceType;
use App\Enums\SitterPreference;
use App\Enums\SpecialConsideration;
use App\Models\AttributeDefinition;
use App\Models\Booking;
use App\Models\BookingGroup;
use App\Models\Hotel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class GuestBookingService
{
    public function create()
    {
        $hotels = Hotel::all()
            ->map(fn ($h) => [
                'id' => $h->id,
                'name' => $h->name,
                'line1' => $h->line1,
                'line2' => $h->line2,
                'city' => $h->city,
                'state' => $h->state,
                'zip' => $h->zip,
            ]);

        $serviceTypes = array_map(
            fn ($case) => ['value' => $case->value, 'label' => $case->label()],
            ServiceType::cases(),
        );

        $locationTypes = array_map(
            fn ($case) => ['value' => $case->value, 'label' => $case->label()],
            LocationType::cases(),
        );

        $sitterPreferences = array_map(
            fn ($case) => ['value' => $case->value, 'label' => $case->label()],
            SitterPreference::cases(),
        );

        return Inertia::render('guest/bookings/create', [
            'service_types' => $serviceTypes,
            'location_types' => $locationTypes,
            'hotels' => $hotels,
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
            'sitter_preferences' => $sitterPreferences,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_first_name' => 'required|string|max:255',
            'client_last_name' => 'required|string|max:255',
            'client_email' => 'required|email|max:255',
            'client_phone' => 'required|string|max:50',
            'service_type' => 'required|string',
            'location_type' => 'required|string',
            'start_datetime' => 'required|date',
            'end_datetime' => 'required|date|after:start_datetime',
            'address_line1' => 'required|string|max:500',
            'address_line2' => 'nullable|string|max:500',
            'address_city' => 'required|string|max:255',
            'address_state' => 'required|string|max:100',
            'address_zip' => 'required|string|max:20',
            'hotel_id' => 'nullable|exists:hotels,id',
            'rental_platform' => 'nullable|string|max:255',
            'special_considerations' => 'array',
            'special_considerations.*' => 'string',
            'caregiver_notes' => 'nullable|string',
            'notes_to_sitterwise' => 'nullable|string',
            'sitter_preferences' => 'array',
            'sitter_preferences.*' => 'string',
            'other_adults_present' => 'nullable|string|max:500',
            'emergency_instructions' => 'nullable|string',
            'special_needs_notes' => 'nullable|string',
            'how_did_you_hear' => 'nullable|string|max:255',
            'new_children' => 'required|array|min:1',
            'new_children.*.name' => 'required|string|max:255',
            'new_children.*.gender' => 'nullable|string|max:20',
            'new_children.*.birth_month' => 'nullable|string',
            'new_children.*.birth_year' => 'nullable|string',
            'new_pets' => 'array',
            'new_pets.*.name' => 'required|string|max:255',
            'new_pets.*.type' => 'nullable|string|max:100',
            'new_pets.*.breed' => 'nullable|string|max:100',
            'new_pets.*.notes' => 'nullable|string',
        ]);

        $startDate = new \DateTime($validated['start_datetime']);
        $endDate = new \DateTime($validated['end_datetime']);
        $diffMs = $endDate->getTimeStamp() - $startDate->getTimeStamp();
        $diffHours = $diffMs / (1000 * 60 * 60);
        if ($diffHours < 4) {
            return back()->withErrors(['end_datetime' => 'Booking must be at least 4 hours long.'])->withInput();
        }

        $client = $this->findOrCreateClient($validated);

        $booking = DB::transaction(function () use ($validated, $client) {
            $bookingGroup = BookingGroup::create([
                'client_id' => $client->id,
                'submitted_at' => now(),
                'submission_type' => 'guest',
                'is_split' => false,
            ]);

            $booking = Booking::create([
                'booking_group_id' => $bookingGroup->id,
                'client_id' => $client->id,
                'service_type' => $validated['service_type'],
                'location_type' => $validated['location_type'],
                'start_datetime' => $validated['start_datetime'],
                'end_datetime' => $validated['end_datetime'],
                'address_line1' => $validated['address_line1'],
                'address_line2' => $validated['address_line2'] ?? null,
                'address_city' => $validated['address_city'],
                'address_state' => $validated['address_state'],
                'address_zip' => $validated['address_zip'],
                'hotel_id' => $validated['hotel_id'] ?? null,
                'rental_platform' => $validated['rental_platform'] ?? null,
                'special_considerations' => $validated['special_considerations'] ?? [],
                'caregiver_notes' => $validated['caregiver_notes'] ?? null,
                'notes_to_sitterwise' => $validated['notes_to_sitterwise'] ?? null,
                'sitter_preferences' => $validated['sitter_preferences'] ?? [],
                'other_adults_present' => $validated['other_adults_present'] ?? null,
                'emergency_instructions' => $validated['emergency_instructions'] ?? null,
                'special_needs_notes' => $validated['special_needs_notes'] ?? null,
                'how_did_you_hear' => $validated['how_did_you_hear'] ?? null,
                'client_first_name' => $validated['client_first_name'],
                'client_last_name' => $validated['client_last_name'],
                'client_phone' => $validated['client_phone'],
                'client_email' => $validated['client_email'],
                'status' => 'pending',
                'payment_status' => 'pending',
                'requires_payment' => true,
                'total_amount' => 0,
            ]);

            if (! empty($validated['new_children'])) {
                foreach ($validated['new_children'] as $childData) {
                    ClientChild::create([
                        'client_id' => $client->id,
                        'name' => $childData['name'] ?? null,
                        'gender' => $childData['gender'] ?? null,
                        'birth_month' => $childData['birth_month'] ? (int) $childData['birth_month'] : null,
                        'birth_year' => $childData['birth_year'] ? (int) $childData['birth_year'] : null,
                    ]);
                }
            }

            if (! empty($validated['new_pets'])) {
                foreach ($validated['new_pets'] as $petData) {
                    ClientPet::create([
                        'client_id' => $client->id,
                        'name' => $petData['name'] ?? null,
                        'type' => $petData['type'] ?? null,
                        'breed' => $petData['breed'] ?? null,
                        'notes' => $petData['notes'] ?? null,
                    ]);
                }
            }

            return $booking;
        });

        return redirect()->route('guest.bookings.confirmation', $booking->id);
    }

    private function findOrCreateClient(array $data): Client
    {
        $user = User::where('email', $data['client_email'])->first();

        if ($user && $user->client) {
            $client = $user->client;
            $client->update([
                'first_name' => $data['client_first_name'],
                'last_name' => $data['client_last_name'],
                'phone' => $data['client_phone'],
            ]);

            return $client;
        }

        if ($user) {
            $user->update([
                'first_name' => $data['client_first_name'],
                'last_name' => $data['client_last_name'],
                'phone' => $data['client_phone'],
            ]);

            $client = $user->client;
            if ($client) {
                return $client;
            }

            return Client::create([
                'user_id' => $user->id,
                'first_name' => $data['client_first_name'],
                'last_name' => $data['client_last_name'],
                'phone' => $data['client_phone'],
            ]);
        }

        $tempPassword = Str::random(16);
        $user = User::create([
            'name' => $data['client_first_name'].' '.$data['client_last_name'],
            'email' => $data['client_email'],
            'password' => Hash::make($tempPassword),
        ]);

        return Client::create([
            'user_id' => $user->id,
            'first_name' => $data['client_first_name'],
            'last_name' => $data['client_last_name'],
            'phone' => $data['client_phone'],
        ]);
    }
}
