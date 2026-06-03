<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reference_name' => 'required|string|max:255',
            'reference_email' => 'required|email',
            'relationship' => 'nullable|string|max:255',
            'years_known' => 'nullable|string|max:255',
            'is_sponsor' => 'boolean',
            'rating_reliability' => 'nullable|integer|min:1|max:5',
            'rating_trustworthiness' => 'nullable|integer|min:1|max:5',
            'rating_maturity' => 'nullable|integer|min:1|max:5',
            'rating_communication' => 'nullable|integer|min:1|max:5',
            'rating_warmth' => 'nullable|integer|min:1|max:5',
            'rating_overall_recommendation' => 'nullable|integer|min:1|max:5',
            'strengths' => 'nullable|string',
            'concerns' => 'nullable|string',
            'additional_comments' => 'nullable|string',
        ];
    }
}
