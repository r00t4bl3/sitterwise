<?php

namespace App\Http\Requests;

use App\Models\Caregiver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CaregiverConnectStripeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();

        if (! $user || ! $user->caregiver) {
            return false;
        }

        return true;
    }

    public function rules(): array
    {
        return [];
    }

    public function caregiver(): Caregiver
    {
        return Auth::user()->caregiver;
    }
}
