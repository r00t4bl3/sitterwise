<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreZipCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $digits = preg_replace('/\D/', '', (string) $this->input('zip_code'));

        $this->merge([
            'zip_code' => strlen((string) $digits) >= 5 ? substr((string) $digits, 0, 5) : $this->input('zip_code'),
        ]);
    }

    public function rules(): array
    {
        return [
            'zip_code' => ['required', 'digits:5'],
            'area' => ['nullable', 'string', 'max:255'],
            'location_id' => ['nullable', 'exists:locations,id'],
        ];
    }
}
