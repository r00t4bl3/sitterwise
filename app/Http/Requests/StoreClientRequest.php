<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientRequest extends FormRequest
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
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20'],
            'client_type' => ['required', 'in:sd_resident,vacationer,invoiced'],
            'password' => ['required', 'string', 'min:4', 'confirmed'],
            'how_did_you_hear' => ['nullable', 'in:concierge,friend_family,google,returning_client,care_com,other'],
        ];
    }
}
