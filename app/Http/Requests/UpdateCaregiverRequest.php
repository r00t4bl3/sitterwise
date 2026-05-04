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
            'address_line1' => 'nullable|string|max:500',
            'address_line2' => 'nullable|string|max:500',
            'address_city' => 'nullable|string|max:255',
            'address_state' => 'nullable|string|max:2',
            'address_zip' => 'nullable|string|max:20',
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
            'certifications.*.file_path' => 'nullable|string|max:500',
            'certifications.*.notes' => 'nullable|string',
            'cert_files' => 'nullable|array',
            'cert_files.*' => 'nullable|file|mimes:jpeg,png,jpg,gif,webp,pdf|max:5120',
        ];

        return $rules;
    }
}
