<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCaregiverRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'status_id' => 'required|exists:caregiver_statuses,id',
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'phone' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'date_of_birth' => 'nullable|date',
            'rating' => 'nullable|numeric|min:0|max:5',
            'biography' => 'nullable|string',
            'notes' => 'nullable|string',
            'profile_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'specialty_type_ids' => 'nullable|array',
            'specialty_type_ids.*' => 'exists:specialty_types,id',
            'location_ids' => 'nullable|array',
            'location_ids.*' => 'exists:locations,id',
            'preferred_location_id' => 'nullable|exists:locations,id',
            'attribute_values' => 'nullable|array',
            'attribute_values.*' => 'nullable|string',
            'certifications' => 'nullable|array',
            'certifications.*.certification_type_id' => 'required|exists:certification_types,id',
            'certifications.*.expiration_date' => 'nullable|date',
            'certifications.*.verified_at' => 'nullable|date',
        ];

        return $rules;
    }
}
