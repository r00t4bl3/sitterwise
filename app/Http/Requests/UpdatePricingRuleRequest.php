<?php

namespace App\Http\Requests;

use App\Enums\ServiceType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePricingRuleRequest extends FormRequest
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
        return [
            'service_type' => [
                'required',
                'string',
                Rule::in(array_column(ServiceType::cases(), 'value')),
            ],
            'number_of_children' => [
                'nullable',
                'integer',
                'min:1',
                'required_if:is_for_pets,false',
            ],
            'is_for_pets' => ['required', 'boolean'],
            'charge_to_client' => ['required', 'numeric', 'min:0'],
            'charge_to_client_notes' => ['nullable', 'string'],
            'paid_to_caregiver' => ['required', 'numeric', 'min:0'],
            'payment_form' => [
                'required',
                'string',
                Rule::in(['Stripe', 'OnPay (Payroll)']),
            ],
            'sitterwise_cut' => ['required', 'numeric', 'min:0'],
        ];
    }
}
