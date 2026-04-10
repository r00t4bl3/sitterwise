<?php

namespace App\Http\Requests;

use App\Enums\ClientType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'client_type' => ['required', Rule::enum(ClientType::class)],
            'how_did_you_hear' => ['nullable', 'in:concierge,friend_family,google,returning_client,care_com,other'],
            'sitter_preferences' => ['nullable', 'array'],
            'other_adults_present' => ['nullable', 'string', 'max:10'],
            'emergency_instructions' => ['nullable', 'string'],
            'special_needs_notes' => ['nullable', 'string'],
            'attributes' => ['nullable', 'array'],
            'children' => ['nullable', 'array'],
            'children.*.name' => ['nullable', 'string', 'max:255'],
            'children.*.gender' => ['nullable', 'in:male,female,other'],
            'children.*.birth_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'children.*.birth_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'pets' => ['nullable', 'array'],
            'pets.*.name' => ['nullable', 'string', 'max:255'],
            'pets.*.type' => ['nullable', 'string', 'max:255'],
            'pets.*.breed' => ['nullable', 'string', 'max:255'],
            'pets.*.notes' => ['nullable', 'string'],
            'addresses' => ['nullable', 'array'],
            'addresses.*.id' => ['nullable', 'integer', 'exists:client_addresses,id'],
            'addresses.*.label' => ['nullable', 'string', 'max:255'],
            'addresses.*.location_type' => ['nullable', 'in:residence,hotel,vacation_rental,other'],
            'addresses.*.line1' => ['required', 'string', 'max:255'],
            'addresses.*.line2' => ['nullable', 'string', 'max:255'],
            'addresses.*.city' => ['required', 'string', 'max:255'],
            'addresses.*.state' => ['required', 'string', 'max:255'],
            'addresses.*.zip' => ['required', 'string', 'max:20'],
            'addresses.*.is_primary' => ['nullable', 'boolean'],
        ];
    }
}
