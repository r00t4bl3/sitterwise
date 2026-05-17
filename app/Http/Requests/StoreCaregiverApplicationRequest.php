<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCaregiverApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

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
            'education.high_school_name' => 'nullable|string|max:255',
            'education.high_school_graduation_year' => 'nullable|digits:4|integer|min:1950|max:2026',

            // Step 3: Employment & Experience
            'employment_status' => 'nullable|in:full_time,part_time,no,student',
            'experiences' => 'required|array|min:1',
            'experiences.*.start_date' => 'required|date_format:Y-m',
            'experiences.*.end_date' => 'nullable|date_format:Y-m',
            'experiences.*.present' => 'boolean',
            'experiences.*.role' => 'required|string|max:255',
            'experiences.*.organization' => 'required|string|max:255',
            'experiences.*.description' => 'required|string|max:2000',
            'experiences.*.ages_served' => 'required|array|min:1',
            'experiences.*.ages_served.*' => 'in:infant,toddler,preschool,school_age,teen',

            // Step 4: Screening Questions
            'smokes' => 'required|in:yes,no',
            'alcohol' => 'required|in:no,socially,regularly',
            'substance_abuse' => 'required|string|max:2000',
            'limitations' => 'required|string|max:2000',
            'allergic_to_pets' => 'required|in:yes,no',
            'visible_tattoos' => 'required|in:yes,no',
            'authorized_to_work' => 'required|in:yes,no',
            'reliable_vehicle' => 'required|in:yes,no',
            'cpr_certified' => 'required|in:yes,no',
            'cpr_expiration' => 'nullable|date',
            'cpr_card' => 'nullable|file|mimes:pdf,jpeg,png|max:10240',
            'trustline_certified' => 'required|in:yes,no',
            'trustline_upload' => 'nullable|file|mimes:pdf,jpeg,png|max:10240',
            'languages' => 'nullable|string|max:500',
            'has_children' => 'nullable|in:no,yes_at_home,yes_grown',
            'skills.special_needs' => 'boolean',
            'skills.swimming' => 'boolean',
            'skills.driving' => 'boolean',
            'skills.bilingual' => 'boolean',
            'skills.other' => 'nullable|string|max:1000',

            // Step 5: References
            'references' => 'required|array|min:3',
            'references.*.name' => 'required|string|max:255',
            'references.*.email' => 'required|email',
            'references.*.phone' => 'required|string|max:20',
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

            // Step 7: Qualifications, Activities & Bio
            'qualifications' => 'nullable|array',
            'qualifications.*' => 'boolean',
            'things_i_bring' => 'nullable|string|max:2000',
            'bio' => 'required|string|max:5000',
            'interests' => 'nullable|string|max:1000',
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
            $sponsorEmail = $this->input('sponsor.email');
            $applicantEmail = session('verified_email');

            if ($sponsorEmail && $sponsorEmail === $applicantEmail) {
                $validator->errors()->add('sponsor.email', 'Sponsor email cannot match your email address.');
            }

            $references = $this->input('references', []);
            $emails = array_column($references, 'email');
            $uniqueEmails = array_unique($emails);

            if (count($emails) !== count($uniqueEmails)) {
                $validator->errors()->add('references', 'Reference emails must be unique.');
            }

            foreach ($references as $index => $reference) {
                if (isset($reference['email']) && $reference['email'] === $applicantEmail) {
                    $validator->errors()->add("references.{$index}.email", 'Reference email cannot match your email address.');
                }
            }
        });
    }
}
