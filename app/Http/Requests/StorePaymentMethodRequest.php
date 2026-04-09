<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_method_id' => ['required', 'string'],
            'brand' => ['required', 'string'],
            'last4' => ['required', 'string'],
            'exp_month' => ['required', 'integer'],
            'exp_year' => ['required', 'integer'],
        ];
    }
}
