<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCaregiverApplicationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Public access for caregiver applications
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Step 1: Sponsor & Personal
            'sponsor.first_name' => 'required|string|max:255',
            'sponsor.last_name' => 'required|string|max:255',
            'sponsor.email' => 'required|email',
            'sponsor.phone' => 'nullable|string|max:20',
            'sponsor.relationship' => 'nullable|string|max:255',
            'personal.first_name' => 'required|string|max:255',
            'personal.last_name' => 'required|string|max:255',
            'personal.address_line1' => 'required|string|max:255',
            'personal.address_line2' => 'nullable|string|max:255',
            'personal.address_city' => 'required|string|max:255',
            'personal.address_state' => 'required|string|max:255',
            'personal.address_zip' => 'required|string|max:20',
            'personal.phone' => 'required|string|max:20',
            'personal.dob' => 'required|date|before:-18 years',
            'personal.photo' => 'nullable|image|max:5120',

            // Step 2: Position & Availability
            'position.babysitting' => 'boolean',
            'position.petsitting' => 'boolean',
            'position.group_events' => 'boolean',
            'availability.weekday_mornings' => 'boolean',
            'availability.weekday_afternoons' => 'boolean',
            'availability.weekday_evenings' => 'boolean',
            'availability.weekends' => 'boolean',
            'availability.overnights' => 'boolean',
            'availability.notes' => 'nullable|string|max:1000',
            'education.level' => 'required|in:high_school,associate,bachelor,master,phd',
            'education.college' => 'nullable|string|max:255',
            'education.graduation_year' => 'nullable|digits:4|integer|min:1980|max:2026',
            'education.degree' => 'nullable|string|max:255',

            // Step 3: Experience
            'experiences' => 'required|array|min:1',
            'experiences.*.start_date' => 'required|date',
            'experiences.*.end_date' => 'nullable|date|after_or_equal:experiences.*.start_date',
            'experiences.*.present' => 'boolean',
            'experiences.*.role' => 'required|string|max:255',
            'experiences.*.organization' => 'required|string|max:255',
            'experiences.*.description' => 'required|string|max:2000',
            'experiences.*.ages_served' => 'required|array|min:1',
            'experiences.*.ages_served.*' => 'in:infant,toddler,preschool,school_age,teen',

            // Step 4: Certifications & Skills
            'certifications' => 'nullable|array',
            'certifications.*' => 'exists:certification_types,id',
            'certification_files.*' => 'nullable|file|mimes:pdf,jpeg,png|max:10240',
            'skills.special_needs' => 'boolean',
            'skills.work_from_home' => 'boolean',
            'skills.swimming' => 'boolean',
            'skills.driving' => 'boolean',
            'skills.other' => 'nullable|string|max:1000',

            // Step 5: References
            'references' => 'required|array|min:3',
            'references.*.name' => 'required|string|max:255',
            'references.*.email' => 'required|email',
            'references.*.phone' => 'nullable|string|max:20',
            'references.*.relationship' => 'required|string|max:255',
            'references.*.years_known' => 'required|in:<1,1-3,3-5,5-10,10+',

            // Step 6: Location & Age Groups
            'location.north_county' => 'boolean',
            'location.south_east_county' => 'boolean',
            'location.flexible' => 'boolean',
            'age_groups.babies' => 'boolean',
            'age_groups.toddlers' => 'boolean',
            'age_groups.preschool' => 'boolean',
            'age_groups.school_age' => 'boolean',

            // Step 7: Review (terms)
            'terms.agree' => 'required|accepted',

            // Step 8: Agreements
            'verification.signature' => 'required|string|max:255',
            'verification.agree' => 'required|accepted',
            'agreement.signature' => 'required|string|max:255',
            'agreement.agree' => 'required|accepted',
        ];
    }

    public function messages(): array
    {
        return [
            'personal.dob.before' => 'You must be at least 18 years old to apply.',
            'references.min' => 'Please provide at least 3 references (plus your sponsor).',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Check sponsor email doesn't match applicant email
            $sponsorEmail = $this->input('sponsor.email');
            $applicantEmail = session('verified_email');

            if ($sponsorEmail && $sponsorEmail === $applicantEmail) {
                $validator->errors()->add('sponsor.email', 'Sponsor email cannot match your email address.');
            }

            // Check for duplicate reference emails
            $references = $this->input('references', []);
            $emails = array_column($references, 'email');
            $uniqueEmails = array_unique($emails);

            if (count($emails) !== count($uniqueEmails)) {
                $validator->errors()->add('references', 'Reference emails must be unique.');
            }

            // Check reference emails don't match applicant email
            foreach ($references as $index => $reference) {
                if (isset($reference['email']) && $reference['email'] === $applicantEmail) {
                    $validator->errors()->add("references.{$index}.email", 'Reference email cannot match your email address.');
                }
            }
        });
    }
}
