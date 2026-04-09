<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHotelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string'],
            'line1' => ['required', 'string'],
            'line2' => ['nullable', 'string'],
            'city' => ['required', 'string'],
            'state' => ['required', 'string'],
            'zip' => ['required', 'string'],
            'parking_instructions' => ['required', 'string'],
            'hourly_rate' => ['required', 'numeric', 'min:0'],
            'resort_fee' => ['nullable', 'numeric', 'min:0'],
            'contact_name' => ['nullable', 'string'],
            'contact_phone' => ['nullable', 'string'],
            'admin_notes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }
}
