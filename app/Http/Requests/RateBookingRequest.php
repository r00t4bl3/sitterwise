<?php

namespace App\Http\Requests;

use App\Models\BookingRating;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RateBookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled in the controller for better context
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rating' => 'required|numeric|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
            'type' => 'required|string|in:'.BookingRating::TYPE_CLIENT_TO_CAREGIVER.','.BookingRating::TYPE_CAREGIVER_TO_CLIENT,
        ];
    }
}
