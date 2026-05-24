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
            'relationship' => 'required|string|max:255',
            'years_known' => 'required|in:<1,1-3,3-5,5-10,10+',
            'rating_reliability' => 'required|integer|min:1|max:5',
            'rating_trustworthiness' => 'required|integer|min:1|max:5',
            'rating_maturity' => 'required|integer|min:1|max:5',
            'rating_communication' => 'required|integer|min:1|max:5',
            'rating_warmth' => 'required|integer|min:1|max:5',
            'rating_overall_recommendation' => 'required|integer|min:1|max:5',
            'strengths' => 'required|string|max:5000',
            'concerns' => 'nullable|string|max:5000',
            'additional_comments' => 'nullable|string|max:5000',
        ];
    }

    public function messages(): array
    {
        return [
            'relationship.required' => 'Please describe your relationship with the applicant.',
            'years_known.required' => 'Please indicate how long you have known the applicant.',
            'strengths.required' => 'Please describe the applicant\'s greatest strengths.',
        ];
    }
}
