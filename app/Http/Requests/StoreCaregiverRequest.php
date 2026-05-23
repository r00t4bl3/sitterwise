<?php

namespace App\Http\Requests;

use App\Enums\CaregiverStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreCaregiverRequest extends FormRequest
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
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'date_of_birth' => ['nullable', 'date'],
            'status' => ['required', new Enum(CaregiverStatus::class)],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'biography' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
