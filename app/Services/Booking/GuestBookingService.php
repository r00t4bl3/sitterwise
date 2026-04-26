<?php

namespace App\Services\Booking;

use App\Enums\LocationType;
use App\Enums\ServiceType;
use App\Enums\SitterPreference;
use App\Enums\SpecialConsideration;
use App\Models\AttributeDefinition;
use App\Models\Booking;
use App\Models\BookingGroup;
use App\Models\Client;
use App\Models\ClientChild;
use App\Models\ClientPet;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Stripe\StripeClient;

class GuestBookingService
{
    private const PENDING_KEY = 'guest_booking_pending';

    private const PAYMENT_TOKEN_KEY = 'guest_booking_payment_token';

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

    public function validateOnly(Request $request)
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
            'new_pets.*.name' => 'nullable|string|max:255',
            'new_pets.*.type' => 'nullable|string|max:100',
            'new_pets.*.breed' => 'nullable|string|max:100',
            'new_pets.*.notes' => 'nullable|string',
        ]);

        $startDate = new \DateTime($validated['start_datetime']);
        $endDate = new \DateTime($validated['end_datetime']);
        $diffHours = $startDate->diff($endDate)->h + ($startDate->diff($endDate)->days * 24);
        if ($diffHours < 4) {
            return back()->withErrors(['end_datetime' => 'Booking must be at least 4 hours long.'])->withInput();
        }

        $paymentToken = Str::ulid();
        $request->session()->put(self::PENDING_KEY, $validated);
        $request->session()->put(self::PAYMENT_TOKEN_KEY, $paymentToken);

        return redirect()->route('guest.bookings.payment', $paymentToken);
    }

    public function processPayment(Request $request, string $token)
    {
        $pendingData = $request->session()->get(self::PENDING_KEY);
        $sessionToken = $request->session()->get(self::PAYMENT_TOKEN_KEY);

        if (! $pendingData || $sessionToken !== $token) {
            return redirect()->route('guest.bookings.create')
                ->with('error', 'Your session has expired. Please try again.');
        }

        $request->validate([
            'payment_method_id' => 'required|string',
        ]);

        $paymentMethodId = $request->input('payment_method_id');

        try {
            $booking = DB::transaction(function () use ($pendingData, $paymentMethodId) {
                $client = $this->createClient($pendingData, $paymentMethodId);
                $bookingGroup = BookingGroup::create([
                    'client_id' => $client->id,
                    'submitted_at' => now(),
                    'submission_type' => 'guest',
                    'is_split' => false,
                ]);

                $booking = Booking::create([
                    'booking_group_id' => $bookingGroup->id,
                    'client_id' => $client->id,
                    'service_type' => $pendingData['service_type'],
                    'location_type' => $pendingData['location_type'],
                    'start_datetime' => $pendingData['start_datetime'],
                    'end_datetime' => $pendingData['end_datetime'],
                    'address_line1' => $pendingData['address_line1'],
                    'address_line2' => $pendingData['address_line2'] ?? null,
                    'address_city' => $pendingData['address_city'],
                    'address_state' => $pendingData['address_state'],
                    'address_zip' => $pendingData['address_zip'],
                    'hotel_id' => $pendingData['hotel_id'] ?? null,
                    'rental_platform' => $pendingData['rental_platform'] ?? null,
                    'special_considerations' => $pendingData['special_considerations'] ?? [],
                    'caregiver_notes' => $pendingData['caregiver_notes'] ?? null,
                    'notes_to_sitterwise' => $pendingData['notes_to_sitterwise'] ?? null,
                    'sitter_preferences' => $pendingData['sitter_preferences'] ?? [],
                    'other_adults_present' => $pendingData['other_adults_present'] ?? null,
                    'emergency_instructions' => $pendingData['emergency_instructions'] ?? null,
                    'special_needs_notes' => $pendingData['special_needs_notes'] ?? null,
                    'how_did_you_hear' => $pendingData['how_did_you_hear'] ?? null,
                    'client_first_name' => $pendingData['client_first_name'],
                    'client_last_name' => $pendingData['client_last_name'],
                    'client_phone' => $pendingData['client_phone'],
                    'client_email' => $pendingData['client_email'],
                    'status' => 'received',
                    'payment_status' => 'pending',
                    'requires_payment' => true,
                    'total_amount' => 0,
                ]);

                if (! empty($pendingData['new_children'])) {
                    foreach ($pendingData['new_children'] as $childData) {
                        ClientChild::create([
                            'client_id' => $client->id,
                            'name' => $childData['name'] ?? null,
                            'gender' => $childData['gender'] ?? null,
                            'birth_month' => $childData['birth_month'] ? (int) $childData['birth_month'] : null,
                            'birth_year' => $childData['birth_year'] ? (int) $childData['birth_year'] : null,
                        ]);
                    }
                }

                if (! empty($pendingData['new_pets'])) {
                    foreach ($pendingData['new_pets'] as $petData) {
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

            $request->session()->forget(self::PENDING_KEY);
            $request->session()->forget(self::PAYMENT_TOKEN_KEY);

            return redirect()->route('guest.bookings.confirmation', $booking->ulid);
        } catch (\Exception $e) {
            Log::error('Guest booking payment failed: '.$e->getMessage());

            return back()->withErrors(['payment' => 'Payment setup failed. Please try again.']);
        }
    }

    public function getPendingData(Request $request): ?array
    {
        return $request->session()->get(self::PENDING_KEY);
    }

    public function getPaymentData(Request $request): array
    {
        $pendingData = $this->getPendingData($request);

        if (! $pendingData) {
            return [];
        }

        return [
            'client_first_name' => $pendingData['client_first_name'],
            'client_last_name' => $pendingData['client_last_name'],
            'client_email' => $pendingData['client_email'],
            'client_phone' => $pendingData['client_phone'],
            'service_type' => $pendingData['service_type'],
            'location_type' => $pendingData['location_type'],
            'start_datetime' => $pendingData['start_datetime'],
            'end_datetime' => $pendingData['end_datetime'],
            'address_line1' => $pendingData['address_line1'],
            'address_city' => $pendingData['address_city'],
            'address_state' => $pendingData['address_state'],
            'address_zip' => $pendingData['address_zip'],
            'hotel_name' => $pendingData['hotel_id'] ?? null,
        ];
    }

    public function createBookingWithPayment(array $pendingData, string $paymentMethodId): Booking
    {
        return DB::transaction(function () use ($pendingData, $paymentMethodId) {
            $client = $this->createClient($pendingData, $paymentMethodId);

            $bookingGroup = BookingGroup::create([
                'client_id' => $client->id,
                'submitted_at' => now(),
                'submission_type' => 'guest',
                'is_split' => false,
            ]);

            $booking = Booking::create([
                'booking_group_id' => $bookingGroup->id,
                'client_id' => $client->id,
                'service_type' => $pendingData['service_type'],
                'location_type' => $pendingData['location_type'],
                'start_datetime' => $pendingData['start_datetime'],
                'end_datetime' => $pendingData['end_datetime'],
                'address_line1' => $pendingData['address_line1'],
                'address_line2' => $pendingData['address_line2'] ?? null,
                'address_city' => $pendingData['address_city'],
                'address_state' => $pendingData['address_state'],
                'address_zip' => $pendingData['address_zip'],
                'hotel_id' => $pendingData['hotel_id'] ?? null,
                'rental_platform' => $pendingData['rental_platform'] ?? null,
                'special_considerations' => $pendingData['special_considerations'] ?? [],
                'caregiver_notes' => $pendingData['caregiver_notes'] ?? null,
                'notes_to_sitterwise' => $pendingData['notes_to_sitterwise'] ?? null,
                'sitter_preferences' => $pendingData['sitter_preferences'] ?? [],
                'other_adults_present' => $pendingData['other_adults_present'] ?? null,
                'emergency_instructions' => $pendingData['emergency_instructions'] ?? null,
                'special_needs_notes' => $pendingData['special_needs_notes'] ?? null,
                'how_did_you_hear' => $pendingData['how_did_you_hear'] ?? null,
                'client_first_name' => $pendingData['client_first_name'],
                'client_last_name' => $pendingData['client_last_name'],
                'client_phone' => $pendingData['client_phone'],
                'client_email' => $pendingData['client_email'],
                'status' => 'received',
                'payment_status' => 'pending',
                'requires_payment' => true,
                'total_amount' => 0,
            ]);

            if (! empty($pendingData['new_children'])) {
                foreach ($pendingData['new_children'] as $childData) {
                    ClientChild::create([
                        'client_id' => $client->id,
                        'name' => $childData['name'] ?? null,
                        'gender' => $childData['gender'] ?? null,
                        'birth_month' => $childData['birth_month'] ? (int) $childData['birth_month'] : null,
                        'birth_year' => $childData['birth_year'] ? (int) $childData['birth_year'] : null,
                    ]);
                }
            }

            if (! empty($pendingData['new_pets'])) {
                foreach ($pendingData['new_pets'] as $petData) {
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
    }

    public function createSetupIntent(Request $request): array
    {
        $pendingData = $this->getPendingData($request);

        if (! $pendingData) {
            return [];
        }

        $paymentToken = $request->session()->get(self::PAYMENT_TOKEN_KEY);

        $stripe = new StripeClient(config('services.stripe.secret'));

        $checkoutSession = $stripe->checkout->sessions->create([
            'ui_mode' => 'embedded_page',
            'mode' => 'setup',
            'payment_method_types' => ['card'],
            'customer_email' => $pendingData['client_email'],
            'return_url' => config('app.url').'/book/payment/'.$paymentToken.'?session_id={CHECKOUT_SESSION_ID}',
        ]);

        return [
            'client_secret' => $checkoutSession->client_secret,
            'session_id' => $checkoutSession->id,
        ];
    }

    public function processSetupSession(Request $request, string $sessionId): ?array
    {
        $stripe = new StripeClient(config('services.stripe.secret'));

        try {
            $session = $stripe->checkout->sessions->retrieve($sessionId);

            if ($session->status === 'complete' && $session->setup_intent) {
                $setupIntent = $stripe->setupIntents->retrieve($session->setup_intent);

                if ($setupIntent->status === 'succeeded' && $setupIntent->payment_method) {
                    return [
                        'payment_method_id' => $setupIntent->payment_method,
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to process guest setup session: '.$e->getMessage());
        }

        return null;
    }

    public function checkSetupSessionComplete(string $clientSecret): ?array
    {
        $stripe = new StripeClient(config('services.stripe.secret'));

        try {
            $session = $stripe->checkout->sessions->retrieve($clientSecret);

            if ($session->status === 'complete' && $session->setup_intent) {
                $setupIntent = $stripe->setupIntents->retrieve($session->setup_intent);

                if ($setupIntent->status === 'succeeded' && $setupIntent->payment_method) {
                    return [
                        'payment_method_id' => $setupIntent->payment_method,
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to check guest setup session: '.$e->getMessage());
        }

        return null;
    }

    private function createClient(array $data, string $paymentMethodId): Client
    {
        $user = User::where('email', $data['client_email'])->first();

        if ($user) {
            $user->update([
                'first_name' => $data['client_first_name'],
                'last_name' => $data['client_last_name'],
                'phone' => $data['client_phone'],
            ]);

            $client = $user->client;
            if ($client) {
                $this->attachPaymentMethod($client, $paymentMethodId);

                return $client;
            }

            $client = Client::create([
                'user_id' => $user->id,
                'first_name' => $data['client_first_name'],
                'last_name' => $data['client_last_name'],
                'phone' => $data['client_phone'],
            ]);

            $this->attachPaymentMethod($client, $paymentMethodId);

            return $client;
        }

        $tempPassword = Str::random(16);
        $user = User::create([
            'name' => $data['client_first_name'].' '.$data['client_last_name'],
            'email' => $data['client_email'],
            'password' => Hash::make($tempPassword),
            'role' => 'client',
        ]);

        $client = Client::create([
            'user_id' => $user->id,
            'first_name' => $data['client_first_name'],
            'last_name' => $data['client_last_name'],
            'phone' => $data['client_phone'],
        ]);

        $this->attachPaymentMethod($client, $paymentMethodId);

        return $client;
    }

    private function attachPaymentMethod(Client $client, string $paymentMethodId): void
    {
        $stripe = new StripeClient(config('services.stripe.secret'));

        try {
            $pm = $stripe->paymentMethods->retrieve($paymentMethodId);

            $client->paymentMethods()->create([
                'provider' => 'stripe',
                'provider_method_id' => $pm->id,
                'brand' => $pm->card->brand,
                'last4' => $pm->card->last4,
                'exp_month' => $pm->card->exp_month,
                'exp_year' => $pm->card->exp_year,
                'status' => 'active',
                'is_default' => $client->paymentMethods()->count() === 0,
            ]);

            $client->update([
                'stripe_customer_id' => $pm->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to attach payment method: '.$e->getMessage());
        }
    }
}
