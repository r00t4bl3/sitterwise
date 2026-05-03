<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'rating' => ['required', 'numeric', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'tip' => ['nullable', 'numeric', 'min:0'],
            'payment_method_id' => ['nullable', 'string'],
        ];
    }
}
