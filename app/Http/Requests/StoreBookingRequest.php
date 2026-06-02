<?php

namespace App\Http\Requests;

use App\Rules\MinimumBookingDuration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $user = $this->user();

                if (! in_array($user?->role, ['admin', 'client'], true)) {
                    return;
                }

                $serviceType = $this->input('service_type');

                if ($serviceType !== 'group_childcare_invoiced') {
                    $childIds = $this->input('child_ids', []);
                    $newChildren = $this->input('new_children', []);

                    if (empty($childIds) && empty($newChildren)) {
                        $validator->errors()->add(
                            'child_ids',
                            'At least one child is required.',
                        );
                    }
                }
            },
        ];
    }

    public function rules(): array
    {
        return match ($this->user()->role) {
            'admin' => $this->adminRules(),
            'client' => $this->clientRules(),
            default => [],
        };
    }

    private function adminRules()
    {
        return [
            'client_id' => ['nullable', 'required_without:new_client.first_name', 'exists:clients,id'],
            'service_type' => ['required', 'string'],
            'location_type' => ['required', 'string'],
            'start_datetime' => ['required', 'date', 'after:now'],
            'end_datetime' => ['required', 'date', 'after:start_datetime'],
            'dates' => ['nullable', 'array', 'min:1'],
            'dates.*.start_datetime' => ['required', 'date'],
            'dates.*.end_datetime' => ['required', 'date', 'after:dates.*.start_datetime'],
            'hotel_id' => ['nullable', 'exists:hotels,id'],
            'hotel_name' => ['nullable', 'string', 'max:255'],
            'address_id' => ['nullable', 'exists:client_addresses,id'],
            'caregiver_id' => ['nullable', 'exists:caregivers,id'],
            'caregiver_notes' => ['nullable', 'string'],
            'notes_to_sitterwise' => ['nullable', 'string'],
            'admin_notes' => ['nullable', 'string'],
            'corporate_id' => ['nullable', 'string'],
            'how_did_you_hear' => ['nullable', 'string'],
            'sitter_preferences' => ['nullable', 'array'],
            'other_adults_present' => ['nullable', 'string'],
            'special_needs_notes' => ['nullable', 'string'],
            'emergency_instructions' => ['nullable', 'string'],
            'requires_payment' => ['nullable', 'boolean'],
            'status' => ['required', 'string'],
            'payment_status' => ['required', 'string'],
            'rental_platform' => ['nullable', 'string'],
            'address_line1' => ['required', 'string'],
            'address_line2' => ['nullable', 'string'],
            'address_city' => ['required', 'string'],
            'address_state' => ['required', 'string'],
            'address_zip' => ['required', 'string'],
            'new_client' => ['nullable', 'array'],
            'new_client.first_name' => ['nullable', 'required_without:client_id', 'string'],
            'new_client.last_name' => ['nullable', 'string'],
            'new_client.email' => ['nullable', 'email'],
            'new_client.phone' => ['nullable', 'string'],
            'new_client.client_type' => ['nullable', 'string'],
            'child_ids' => ['nullable', 'array'],
            'child_ids.*' => ['integer', 'exists:client_children,id'],
            'pet_ids' => ['nullable', 'array'],
            'pet_ids.*' => ['integer', 'exists:client_pets,id'],
            'new_children' => ['nullable', 'array'],
            'new_pets' => ['nullable', 'array'],
            'save_children_pets_to_profile' => ['nullable', 'boolean'],
            'children_notes' => ['nullable', 'string'],
        ];
    }

    private function clientRules()
    {
        return [
            'service_type' => ['required', 'string'],
            'location_type' => ['required', 'string'],
            'start_datetime' => ['required', 'date', 'after:now'],
            'end_datetime' => ['required', 'date', 'after:start_datetime', new MinimumBookingDuration],
            'dates' => ['nullable', 'array', 'min:1'],
            'dates.*.start_datetime' => ['required', 'date'],
            'dates.*.end_datetime' => ['required', 'date', 'after:dates.*.start_datetime'],
            'address_line1' => ['required_if:location_type,private_home,vacation_rental,event_venue', 'string', 'nullable'],
            'address_line2' => ['nullable', 'string'],
            'address_city' => ['required_if:location_type,private_home,vacation_rental,event_venue', 'string', 'nullable'],
            'address_state' => ['required_if:location_type,private_home,vacation_rental,event_venue', 'string', 'nullable'],
            'address_zip' => ['required_if:location_type,private_home,vacation_rental,event_venue', 'string', 'nullable'],
            'caregiver_notes' => ['nullable', 'string'],
            'notes_to_sitterwise' => ['nullable', 'string'],
            'how_did_you_hear' => ['nullable', 'string'],
            'sitter_preferences' => ['nullable', 'array'],
            'other_adults_present' => ['nullable', 'string'],
            'emergency_instructions' => ['nullable', 'string'],
            'hotel_id' => ['required_if:location_type,hotel', 'nullable', 'exists:hotels,id'],
            'address_id' => ['nullable', 'exists:client_addresses,id'],
            'rental_platform' => ['required_if:location_type,vacation_rental', 'nullable', 'string'],
            'special_needs_notes' => ['nullable', 'string'],
            'save_children_pets_to_profile' => ['nullable', 'boolean'],
            'new_children' => ['nullable', 'array'],
            'new_pets' => ['nullable', 'array'],
            'deleted_child_ids' => ['nullable', 'array'],
            'deleted_pet_ids' => ['nullable', 'array'],
            'children_notes' => ['nullable', 'string'],
        ];
    }
}
