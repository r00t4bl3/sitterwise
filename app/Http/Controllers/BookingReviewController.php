<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Http\Requests\StoreReviewRequest;
use App\Models\Booking;
use App\Models\BookingRating;
use App\Models\Caregiver;
use App\Services\Billing\TipChargeService;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;

class BookingReviewController extends Controller
{
    // ========== FOR LOGGED-IN CLIENTS (from dashboard) ==========

    public function create(Booking $booking)
    {
        $client = request()->user()->client;

        if ($booking->client_id !== $client->id) {
            abort(403, 'Unauthorized');
        }

        return $this->getReviewData($booking, true, $client);
    }

    public function store(StoreReviewRequest $request, Booking $booking)
    {
        $client = request()->user()->client;

        if ($booking->client_id !== $client->id || ! in_array($booking->status, [BookingStatus::Completed->value, BookingStatus::Paid->value])) {
            return Redirect::back()->with('error', 'Unauthorized or booking not completed or paid');
        }

        $paymentMethodId = $request->input('payment_method_id');

        return $this->processReviewSubmission($request, $booking, $request->user()->id, true, $paymentMethodId);
    }

    // ========== FOR GUEST/NON-LOGGED-IN CLIENTS (from signed email link) ==========

    public function createFromLink(Booking $booking)
    {
        return $this->getReviewData($booking, false, null);
    }

    public function storeFromLink(StoreReviewRequest $request, Booking $booking)
    {
        if (! in_array($booking->status, [BookingStatus::Completed->value, BookingStatus::Paid->value])) {
            return Redirect::back()->with('error', 'Booking not completed or paid');
        }

        $raterId = $booking->client?->user_id;
        $paymentMethodId = $request->input('payment_method_id');

        return $this->processReviewSubmission($request, $booking, $raterId, false, $paymentMethodId);
    }

    // ========== SHARED PRIVATE METHODS ==========

    private function getReviewData(Booking $booking, $isLoggedInClient = true, $client = null)
    {
        if (! in_array($booking->status, [BookingStatus::Completed->value, BookingStatus::Paid->value])) {
            abort(403, 'Reviews are only available for completed or paid bookings');
        }

        $booking->load('caregiver', 'caregiverRating');
        $existingRating = $booking->getRelation('caregiverRating');

        if ($isLoggedInClient) {
            $viewPage = 'client/reviews/create';
            $hasDefaultPaymentMethod = $client->hasPaymentMethod();
            $hasStripeCustomerId = ! empty($client->stripe_customer_id);
        } else {
            $viewPage = 'guest/bookings/review';
            $hasDefaultPaymentMethod = false;
            $hasStripeCustomerId = false;
        }

        return Inertia::render($viewPage, [
            'booking' => [
                'ulid' => $booking->ulid,
                'start_datetime' => $booking->start_datetime,
                'end_datetime' => $booking->end_datetime,
                'caregiver_name' => $booking->caregiver
                    ? $booking->caregiver->first_name.' '.$booking->caregiver->last_name
                    : 'Unknown',
                'existing_rating' => $existingRating?->rating,
                'existing_comment' => $existingRating?->comment,
                'existing_tip' => $booking->tip,
            ],
            'has_default_payment_method' => $hasDefaultPaymentMethod,
            'has_stripe_customer_id' => $hasStripeCustomerId,
        ]);
    }

    private function processReviewSubmission(StoreReviewRequest $request, Booking $booking, $raterId, $isLoggedInClient = true, ?string $paymentMethodId = null)
    {
        BookingRating::updateOrCreate(
            [
                'booking_id' => $booking->id,
                'rater_id' => $raterId,
                'ratable_type' => Caregiver::class,
                'ratable_id' => $booking->caregiver_id,
            ],
            [
                'rating' => $request->rating,
                'comment' => $request->comment,
            ]
        );

        if ($request->filled('tip') && $request->tip > 0) {
            $tipService = new TipChargeService;
            $tipResult = $tipService->charge($booking, (float) $request->tip, $paymentMethodId);

            if (! $tipResult['success']) {
                return Redirect::back()->with('error', 'Review saved, but tip failed: '.$tipResult['message']);
            }
        }

        if (! $isLoggedInClient) {
            return Inertia::render('guest/bookings/review-success', [
                'caregiver_name' => $booking->caregiver
                    ? $booking->caregiver->first_name.' '.$booking->caregiver->last_name
                    : 'Unknown',
                'tip_amount' => (float) $request->tip,
            ]);
        }

        return to_route('dashboard')->with('success', 'Review submitted successfully!');
    }
}
