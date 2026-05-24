<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInterviewEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && ($this->user()->isAdmin() || $this->user()->isSuperAdmin());
    }

    public function rules(): array
    {
        return [
            'scores' => 'required|array',
            'scores.soft_skills' => 'required|array',
            'scores.soft_skills.confidence' => 'required|integer|min:1|max:4',
            'scores.soft_skills.warmth' => 'required|integer|min:1|max:4',
            'scores.soft_skills.experience' => 'required|integer|min:1|max:4',
            'scores.soft_skills.communicativeness' => 'required|integer|min:1|max:4',
            'scores.soft_skills.humor' => 'required|integer|min:1|max:4',
            'scores.soft_skills.preparedness' => 'required|integer|min:1|max:4',
            'scores.professionalism' => 'required|array',
            'scores.professionalism.on_time' => 'required|integer|min:1|max:4',
            'scores.professionalism.id_prepared' => 'required|integer|min:1|max:4',
            'scores.professionalism.dress_code' => 'required|integer|min:1|max:4',
            'notes' => 'required|string|max:5000',
            'status' => 'required|in:draft,declined,completed',
        ];
    }

    public function messages(): array
    {
        return [
            'notes.required' => 'Please provide your overall impressions and notes.',
        ];
    }
}
