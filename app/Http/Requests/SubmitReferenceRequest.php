<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitReferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rating' => 'required|integer|min:1|max:5',
            'feedback' => 'required|string|max:5000',
            'relationship' => 'required|string|max:255',
            'years_known' => 'required|in:<1,1-3,3-5,5-10,10+',
        ];
    }

    public function messages(): array
    {
        return [
            'rating.required' => 'Please provide a rating.',
            'rating.min' => 'Rating must be at least 1 star.',
            'rating.max' => 'Rating cannot exceed 5 stars.',
            'feedback.required' => 'Please provide your feedback.',
            'relationship.required' => 'Please describe your relationship with the applicant.',
            'years_known.required' => 'Please indicate how long you have known the applicant.',
        ];
    }
}
