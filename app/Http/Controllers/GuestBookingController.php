<?php

namespace App\Http\Controllers;

use App\Enums\LocationType;
use App\Models\Booking;
use App\Services\Booking\GuestBookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class GuestBookingController extends Controller
{
    public function __construct(
        private GuestBookingService $guestBookingService,
    ) {}

    public function create()
    {
        return $this->guestBookingService->create();
    }

    public function store(Request $request)
    {
        return $this->guestBookingService->validateOnly($request);
    }

    public function payment(Request $request, string $token)
    {
        $bookingData = $this->guestBookingService->getPaymentData($request);

        if (empty($bookingData)) {
            return redirect()->route('guest.bookings.create')
                ->with('error', 'Your session has expired. Please try again.');
        }

        $sessionId = null;

        if ($request->has('session_id')) {
            $sessionId = $request->query('session_id');
            $pendingData = $this->guestBookingService->getPendingData($request);

            if ($pendingData) {
                $paymentResult = $this->guestBookingService->processSetupSession($request, $sessionId);

                if ($paymentResult) {
                    try {
                        $booking = $this->guestBookingService->createBookingWithPayment(
                            $pendingData,
                            $paymentResult['payment_method_id'],
                        );

                        $request->session()->forget('guest_booking_pending');
                        $request->session()->forget('guest_booking_payment_token');

                        return redirect()->route('guest.bookings.confirmation', $booking->ulid);
                    } catch (\Exception $e) {
                        Log::error('Guest booking payment failed: '.$e->getMessage());
                    }
                }
            }
        }

        return Inertia::render('guest/bookings/payment', [
            'booking' => $bookingData,
            'token' => $token,
            'stripe_public_key' => config('services.stripe.public'),
            'session_id' => $sessionId,
            'location_types' => array_map(
                fn ($type) => ['value' => $type->value, 'label' => $type->label()],
                LocationType::cases(),
            ),
        ]);
    }

    public function processPayment(Request $request, string $token)
    {
        $bookingData = $this->guestBookingService->getPaymentData($request);

        if (empty($bookingData)) {
            return redirect()->route('guest.bookings.create')
                ->with('error', 'Your session has expired. Please try again.');
        }

        $setupIntent = $this->guestBookingService->createSetupIntent($request);

        return Inertia::render('guest/bookings/payment', [
            'booking' => $bookingData,
            'token' => $token,
            'stripe_public_key' => config('services.stripe.public'),
            'client_secret' => $setupIntent['client_secret'] ?? null,
            'session_id' => $setupIntent['session_id'] ?? null,
        ]);
    }

    public function getSetupIntent(Request $request, string $token)
    {
        $bookingData = $this->guestBookingService->getPaymentData($request);

        if (empty($bookingData)) {
            return response()->json(['error' => 'Session expired'], 400);
        }

        $setupIntent = $this->guestBookingService->createSetupIntent($request);

        return response()->json($setupIntent);
    }

    public function checkPaymentStatus(Request $request, string $token)
    {
        $request->validate([
            'client_secret' => 'required|string',
        ]);

        $clientSecret = $request->input('client_secret');
        $pendingData = $this->guestBookingService->getPendingData($request);

        if (! $pendingData) {
            return response()->json([
                'success' => false,
                'error' => 'Your session has expired. Please try again.',
            ], 400);
        }

        $paymentResult = $this->guestBookingService->checkSetupSessionComplete($clientSecret);

        if (! $paymentResult) {
            return response()->json([
                'success' => false,
                'complete' => false,
            ]);
        }

        try {
            $booking = $this->guestBookingService->createBookingWithPayment(
                $pendingData,
                $paymentResult['payment_method_id'],
            );

            $request->session()->forget('guest_booking_pending');
            $request->session()->forget('guest_booking_payment_token');

            return response()->json([
                'success' => true,
                'redirect_url' => route('guest.bookings.confirmation', $booking->ulid),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to create booking. Please try again.',
            ], 500);
        }
    }

    public function verifyPayment(Request $request, string $token)
    {
        $request->validate([
            'session_id' => 'required|string',
        ]);

        $pendingData = $this->guestBookingService->getPendingData($request);

        if (! $pendingData) {
            return response()->json([
                'success' => false,
                'error' => 'Your session has expired. Please try again.',
            ], 400);
        }

        $paymentResult = $this->guestBookingService->processSetupSession(
            $request,
            $request->input('session_id'),
        );

        if (! $paymentResult) {
            return response()->json([
                'success' => false,
                'error' => 'Payment setup failed. Please try again.',
            ], 400);
        }

        try {
            $booking = $this->guestBookingService->createBookingWithPayment(
                $pendingData,
                $paymentResult['payment_method_id'],
            );

            $request->session()->forget('guest_booking_pending');
            $request->session()->forget('guest_booking_payment_token');

            return response()->json([
                'success' => true,
                'redirect_url' => route('guest.bookings.confirmation', $booking->ulid),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to create booking. Please try again.',
            ], 500);
        }
    }

    public function confirmation(Booking $booking)
    {
        $booking->load('bookingGroup.bookings');

        $passwordSetupUrl = null;
        $token = session()->get('password_setup_token');
        $email = session()->get('password_setup_email');

        if ($token && $email && $email === $booking->client_email) {
            $passwordSetupUrl = url('/reset-password/'.$token.'?email='.urlencode($email));
        }

        $group = $booking->bookingGroup;
        $siblingBookings = $group?->bookings
            ->filter(fn ($b) => $b->id !== $booking->id)
            ->values()
            ->map(fn ($b) => [
                'id' => $b->id,
                'ulid' => $b->ulid,
                'start_datetime' => $b->start_datetime,
                'end_datetime' => $b->end_datetime,
                'status' => $b->status,
            ]);

        return Inertia::render('guest/bookings/confirmation', [
            'booking' => [
                'id' => $booking->id,
                'ulid' => $booking->ulid,
                'service_type' => $booking->service_type,
                'location_type' => $booking->location_type,
                'start_datetime' => $booking->start_datetime,
                'end_datetime' => $booking->end_datetime,
                'status' => $booking->status,
                'client_first_name' => $booking->client_first_name,
                'client_last_name' => $booking->client_last_name,
                'hotel_name' => $booking->hotel_name ?? $booking->hotel?->name,
                'address_line1' => $booking->address_line1,
                'address_city' => $booking->address_city,
                'address_state' => $booking->address_state,
                'address_zip' => $booking->address_zip,
                'booking_group' => $group ? [
                    'id' => $group->id,
                    'bookings_count' => $group->bookings->count(),
                    'sibling_bookings' => $siblingBookings,
                ] : null,
            ],
            'passwordSetupUrl' => $passwordSetupUrl,
        ]);
    }
}
