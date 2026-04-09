<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCertificationTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', Rule::unique('certification_types', 'name')->ignore($this->certification->id)],
            'description' => ['nullable', 'string'],
            'expires_required' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }
}
