<?php

namespace App\Http\Requests;

use App\Models\Booking;
use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'rating' => ['required', 'numeric', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'tip' => ['nullable', 'numeric', 'min:0', 'max:'.$this->maxTip()],
            'payment_method_id' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'tip.max' => 'The tip may not be larger than the booking amount.',
        ];
    }

    /**
     * The review link is a signed URL that charges the stored card, so the tip
     * must be capped server-side: anyone holding a forwarded link could
     * otherwise charge an arbitrary amount. Cap at the booking's service
     * amount, with a floor of $100 so small bookings can still tip generously.
     */
    protected function maxTip(): float
    {
        $booking = $this->route('booking');

        // Depending on when validation runs, the route parameter may still be
        // the raw ULID/id rather than the bound model.
        if (! $booking instanceof Booking && $booking !== null) {
            $booking = (new Booking)->resolveRouteBinding($booking);
        }

        $serviceAmount = $booking instanceof Booking
            ? (float) $booking->total_service_amount
            : 0.0;

        return max(100.0, $serviceAmount);
    }
}
