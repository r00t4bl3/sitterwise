<?php

namespace App\Http\Requests;

use App\Enums\ClientType;
use App\Enums\DiscoverySource;
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
            'biography' => ['nullable', 'string', 'max:8191'],
            'phone' => ['required', 'string', 'max:20'],
            'client_type' => ['required', Rule::enum(ClientType::class)],
            'how_did_you_hear' => ['nullable', Rule::enum(DiscoverySource::class)],
            'sitter_preferences' => ['nullable', 'array'],
            'other_adults_present' => ['nullable', 'string', 'max:10'],
            'emergency_instructions' => ['nullable', 'string'],
            'special_needs_notes' => ['nullable', 'string'],
            'attributes' => ['nullable', 'array'],
            'children' => ['nullable', 'array'],
            'children.*.name' => ['nullable', 'string', 'max:255'],
            'children.*.gender' => ['nullable', 'in:male,female'],
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
            'addresses.*.location_type' => ['nullable', 'in:private_home,hotel,vacation_rental,event_venue'],
            'addresses.*.line1' => ['nullable', 'string', 'max:255'],
            'addresses.*.line2' => ['nullable', 'string', 'max:255'],
            'addresses.*.city' => ['nullable', 'string', 'max:255'],
            'addresses.*.state' => ['nullable', 'string', 'max:255'],
            'addresses.*.zip' => ['nullable', 'string', 'max:20'],
            'addresses.*.is_primary' => ['nullable', 'boolean'],
            'favorite_caregiver_ids' => ['nullable', 'array'],
            'favorite_caregiver_ids.*' => ['integer', Rule::exists('caregivers', 'id')],
            'blocked_caregiver_ids' => ['nullable', 'array'],
            'blocked_caregiver_ids.*' => ['integer', Rule::exists('caregivers', 'id')],
        ];
    }
}
