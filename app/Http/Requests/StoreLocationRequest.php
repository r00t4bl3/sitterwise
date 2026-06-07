<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'unique:locations,name'],
            'svg_icon' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'cities' => ['nullable', 'array'],
            'cities.*' => ['required', 'string', 'max:255'],
        ];
    }
}
