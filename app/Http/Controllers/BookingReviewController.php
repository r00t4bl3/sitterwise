<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReviewRequest;
use App\Models\Booking;
use App\Models\BookingRating;
use App\Models\Caregiver;
use App\Services\Billing\TipChargeService;
use Illuminate\Support\Facades\Redirect;

class BookingReviewController extends Controller
{
    public function create(Booking $booking)
    {
        $client = request()->user()->client;

        if ($booking->client_id !== $client->id) {
            abort(403, 'Unauthorized');
        }

        if ($booking->status !== 'completed') {
            abort(403, 'Reviews are only available for completed bookings');
        }

        $booking->load('caregiver', 'caregiverRating');
        $existingRating = $booking->getRelation('caregiverRating');

        return inertia('client/reviews/create', [
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
        ]);
    }

    public function store(StoreReviewRequest $request, Booking $booking)
    {
        $client = $request->user()->client;

        if ($booking->client_id !== $client->id || $booking->status !== 'completed') {
            return Redirect::back()->with('error', 'Unauthorized or booking not completed');
        }

        BookingRating::updateOrCreate(
            [
                'booking_id' => $booking->id,
                'rater_id' => $request->user()->id,
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
            $tipResult = $tipService->charge($booking, (float) $request->tip);

            if (! $tipResult['success']) {
                return Redirect::back()->with('error', 'Review saved, but tip failed: '.$tipResult['message']);
            }
        }

        return Redirect::back()->with('success', 'Review submitted successfully!');
    }
}
