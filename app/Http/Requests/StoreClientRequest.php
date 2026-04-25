<?php

namespace App\Http\Requests;

use App\Enums\ClientType;
use App\Enums\DiscoverySource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'biography' => ['nullable', 'string', 'max:8191'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20'],
            'client_type' => ['required', Rule::enum(ClientType::class)],
            'password' => ['required', 'string', 'min:4', 'confirmed'],
            'how_did_you_hear' => ['nullable', Rule::enum(DiscoverySource::class)],
        ];
    }
}
