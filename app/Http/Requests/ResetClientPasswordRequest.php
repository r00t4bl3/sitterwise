<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResetClientPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'new_password' => ['required', 'string', 'min:4', 'confirmed'],
        ];
    }
}
