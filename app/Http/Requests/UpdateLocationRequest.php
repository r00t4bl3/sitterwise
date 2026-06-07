<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', Rule::unique('locations', 'name')->ignore($this->location->id)],
            'svg_icon' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'cities' => ['nullable', 'array'],
            'cities.*' => ['required', 'string', 'max:255'],
        ];
    }
}
