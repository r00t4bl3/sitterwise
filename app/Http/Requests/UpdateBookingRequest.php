<?php

namespace App\Http\Requests;

use App\Rules\MinimumBookingDuration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateBookingRequest extends FormRequest
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
                    $newChildren = $this->input('new_children', []);

                    if (empty($newChildren)) {
                        $validator->errors()->add(
                            'new_children',
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
            'client_id' => ['required', 'exists:clients,id'],
            'service_type' => ['required', 'string'],
            'location_type' => ['required', 'string'],
            'start_datetime' => ['required', 'date'],
            'end_datetime' => ['required', 'date', 'after:start_datetime', new MinimumBookingDuration],
            'hotel_id' => ['nullable', 'exists:hotels,id'],
            'hotel_name' => ['nullable', 'string', 'max:255'],
            'address_id' => ['nullable', 'exists:client_addresses,id'],
            'caregiver_id' => ['nullable', 'exists:caregivers,id'],
            'caregiver_notes' => ['nullable', 'string'],
            'notes_to_sitterwise' => ['nullable', 'string'],
            'admin_notes' => ['nullable', 'string'],
            'corporate_id' => ['nullable', 'string'],
            'sitter_preferences' => ['nullable', 'array'],
            'other_adults_present' => ['nullable', 'string'],
            'special_needs_notes' => ['nullable', 'string'],
            'emergency_instructions' => ['nullable', 'string'],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
            'requires_payment' => ['nullable', 'boolean'],
            'status' => ['required', 'string', Rule::notIn(['cancelled'])],
            'payment_status' => ['required', 'string'],
            'rental_platform' => ['nullable', 'string'],
            'address_line1' => ['nullable', 'string'],
            'address_line2' => ['nullable', 'string'],
            'address_city' => ['nullable', 'string'],
            'address_state' => ['nullable', 'string'],
            'address_zip' => ['nullable', 'string'],
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
            'end_datetime' => ['required', 'date', 'after:start_datetime'],
            'hotel_id' => ['nullable', 'exists:hotels,id'],
            'address_id' => ['nullable', 'exists:client_addresses,id'],
            'caregiver_id' => ['nullable', 'exists:caregivers,id'],
            'caregiver_notes' => ['nullable', 'string'],
            'notes_to_sitterwise' => ['nullable', 'string'],
            'admin_notes' => ['nullable', 'string'],
            'corporate_id' => ['nullable', 'string'],
            'sitter_preferences' => ['nullable', 'array'],
            'other_adults_present' => ['nullable', 'string'],
            'special_needs_notes' => ['nullable', 'string'],
            'emergency_instructions' => ['nullable', 'string'],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
            'requires_payment' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string'],
            'payment_status' => ['nullable', 'string'],
            'rental_platform' => ['nullable', 'string'],
            'address_line1' => ['nullable', 'string'],
            'address_line2' => ['nullable', 'string'],
            'address_city' => ['nullable', 'string'],
            'address_state' => ['nullable', 'string'],
            'address_zip' => ['nullable', 'string'],
            'deleted_child_ids' => ['nullable', 'array'],
            'deleted_child_ids.*' => ['integer', 'exists:client_children,id'],
            'deleted_pet_ids' => ['nullable', 'array'],
            'deleted_pet_ids.*' => ['integer', 'exists:client_pets,id'],
            'new_children' => ['nullable', 'array'],
            'new_pets' => ['nullable', 'array'],
            'save_children_pets_to_profile' => ['nullable', 'boolean'],
            'children_notes' => ['nullable', 'string'],
        ];
    }
}
