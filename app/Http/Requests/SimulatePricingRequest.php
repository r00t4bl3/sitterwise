<?php

namespace App\Http\Requests;

use App\Enums\ServiceType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SimulatePricingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'service_type' => ['required', 'string', Rule::in(array_map(fn ($case) => $case->value, ServiceType::cases()))],
            'number_of_children' => ['nullable', 'integer', 'min:0'],
            'is_for_pets' => ['boolean'],
            'hours' => ['required', 'numeric', 'min:0'],
        ];
    }
}
