<?php

namespace Database\Seeders;

use App\Enums\CaregiverStatus;
use App\Models\Caregiver;
use App\Models\CaregiverApplication;
use App\Models\ReferenceRequest;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ApplicantSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::create([
            'name' => 'Sarah Johnson',
            'email' => 'sarah.johnson@example.com',
            'password' => Hash::make('password'),
            'role' => 'caregiver',
            'email_verified_at' => now(),
        ]);

        $caregiver = Caregiver::create([
            'user_id' => $user->id,
            'status' => CaregiverStatus::Applicant,
            'status_token' => Str::random(32),
            'first_name' => 'Sarah',
            'last_name' => 'Johnson',
            'slug' => 'sarah-johnson-'.Str::random(5),
            'phone' => '(619) 555-0142',
            'address_line1' => '456 Park Blvd',
            'address_line2' => null,
            'address_city' => 'San Diego',
            'address_state' => 'CA',
            'address_zip' => '92101',
            'date_of_birth' => '1995-06-15',
            'languages' => ['spanish', 'tagalog'],
        ]);

        CaregiverApplication::create([
            'caregiver_id' => $caregiver->id,
            'data' => [
                'sponsor' => [
                    'first_name' => 'Maria',
                    'last_name' => 'Garcia',
                    'email' => 'maria.garcia@example.com',
                    'phone' => '(619) 555-0100',
                    'relationship' => 'Former Employer',
                ],
                'personal' => [
                    'first_name' => 'Sarah',
                    'last_name' => 'Johnson',
                    'address_line1' => '456 Park Blvd',
                    'address_line2' => '',
                    'address_city' => 'San Diego',
                    'address_state' => 'CA',
                    'address_zip' => '92101',
                    'phone' => '(619) 555-0142',
                    'email' => 'sarah.johnson@example.com',
                    'dob' => '1995-06-15',
                    'photo' => null,
                ],
                'position' => [
                    'babysitting' => true,
                    'petsitting' => false,
                    'group_events' => true,
                ],
                'availability' => [
                    'weekday_mornings' => true,
                    'weekday_afternoons' => true,
                    'weekday_evenings' => false,
                    'weekends' => true,
                    'overnights' => false,
                    'notes' => 'Available weekdays before 3pm and all day weekends',
                ],
                'education' => [
                    'level' => 'bachelor',
                    'college' => 'San Diego State University',
                    'graduation_year' => '2017',
                    'degree' => 'Child & Family Development',
                    'high_school_name' => '',
                    'high_school_graduation_year' => '',
                ],
                'employment_status' => 'part_time',
                'current_employer' => 'Bright Future Daycare',
                'experiences' => [
                    [
                        'start_date' => '2019-08',
                        'end_date' => '',
                        'present' => true,
                        'role' => 'Lead Childcare Provider',
                        'organization' => 'Bright Future Daycare',
                        'description' => 'Supervised a classroom of 8 toddlers, planned daily activities, communicated with parents about daily progress.',
                        'ages_served' => ['toddlers', 'preschool'],
                    ],
                    [
                        'start_date' => '2017-06',
                        'end_date' => '2019-07',
                        'present' => false,
                        'role' => 'Nanny',
                        'organization' => 'The Chen Family',
                        'description' => 'Full-time nanny for two children ages 4 and 7. Prepared meals, helped with homework, organized playdates.',
                        'ages_served' => ['preschool', 'school_age'],
                    ],
                ],
                'certifications' => [],
                'smokes' => 'no',
                'alcohol' => 'socially',
                'substance_abuse' => 'No history of substance abuse.',
                'limitations' => 'None',
                'allergic_to_pets' => 'no',
                'allergic_to_what' => '',
                'visible_tattoos' => 'yes',
                'tattoo_description' => 'Small flower tattoo on wrist, can be covered with long sleeves.',
                'authorized_to_work' => 'yes',
                'reliable_vehicle' => 'yes',
                'cpr_certified' => 'yes',
                'cpr_expiration' => '2026-12-31',
                'cpr_card' => null,
                'trustline_certified' => 'yes',
                'trustline_upload' => null,
                'languages' => ['spanish', 'tagalog'],
                'has_children' => 'no',
                'children_ages' => '',
                'qualifications' => [
                    'special_needs' => false,
                    'companion_care' => false,
                    'sick_care' => true,
                    'work_from_home' => true,
                    'driving' => true,
                    'dogsitting' => true,
                    'swimming' => true,
                    'overnight_care' => false,
                ],
                'things_i_bring' => 'I bring patience, creativity, and a love for outdoor activities. I believe in gentle guidance and positive reinforcement.',
                'bio' => 'I am a nurturing and experienced childcare provider with over 6 years of professional experience. I am CPR certified and love creating engaging, educational activities for children of all ages.',
                'interests' => 'Hiking, painting, reading children\'s literature, gardening',
                'references' => [
                    [
                        'first_name' => 'Lisa',
                        'last_name' => 'Chen',
                        'email' => 'lisa.chen@example.com',
                        'phone' => '(619) 555-0200',
                        'relationship' => 'Former Employer',
                        'years_known' => '3-5',
                    ],
                    [
                        'first_name' => 'David',
                        'last_name' => 'Thompson',
                        'email' => 'david.thompson@example.com',
                        'phone' => '(619) 555-0300',
                        'relationship' => 'Colleague',
                        'years_known' => '1-3',
                    ],
                    [
                        'first_name' => 'Rachel',
                        'last_name' => 'Kim',
                        'email' => 'rachel.kim@example.com',
                        'phone' => '(619) 555-0400',
                        'relationship' => 'Friend',
                        'years_known' => '5-10',
                    ],
                ],
                'location' => [
                    'north_county' => false,
                    'south_east_county' => true,
                    'flexible' => true,
                ],
                'age_groups' => [
                    'babies' => false,
                    'toddlers' => true,
                    'preschool' => true,
                    'school_age' => true,
                ],
                'terms' => ['agree' => true],
                'verification' => [
                    'signature' => 'Sarah Johnson',
                    'agree' => true,
                ],
                'agreement' => [
                    'signature' => 'Sarah Johnson',
                    'agree' => true,
                ],
            ],
            'submitted_at' => now()->subDays(2),
        ]);

        // Sponsor reference
        ReferenceRequest::create([
            'token' => Str::random(32),
            'caregiver_id' => $caregiver->id,
            'reference_name' => 'Maria Garcia',
            'reference_email' => 'maria.garcia@example.com',
            'relationship' => 'Former Employer',
            'years_known' => '3-5',
            'is_sponsor' => true,
            'rating_reliability' => 5,
            'rating_trustworthiness' => 5,
            'rating_maturity' => 4,
            'rating_communication' => 5,
            'rating_warmth' => 5,
            'rating_overall_recommendation' => 5,
            'strengths' => "Sarah is one of the most reliable and nurturing caregivers I've ever hired. She was always punctual, communicative, and went above and beyond for my children.",
            'concerns' => null,
            'additional_comments' => 'I would absolutely recommend Sarah to any family looking for a trustworthy caregiver.',
            'submitted_at' => now()->subDay(),
        ]);

        // Additional references
        // Additional references
        $refs = [
            [
                'name' => 'Lisa Chen',
                'email' => 'lisa.chen@example.com',
                'relationship' => 'Former Employer',
                'years_known' => '3-5',
                'submitted_at' => now()->subHours(12),
                'rating_reliability' => 4,
                'rating_trustworthiness' => 5,
                'rating_maturity' => 4,
                'rating_communication' => 4,
                'rating_warmth' => 5,
                'rating_overall_recommendation' => 4,
                'strengths' => 'Sarah has a natural gift with children. She is patient, creative, and knows how to handle difficult situations with grace.',
                'concerns' => 'Sometimes overcommits herself, but always follows through.',
                'additional_comments' => null,
            ],
            [
                'name' => 'David Thompson',
                'email' => 'david.thompson@example.com',
                'relationship' => 'Colleague',
                'years_known' => '1-3',
                'submitted_at' => null,
            ],
            [
                'name' => 'Rachel Kim',
                'email' => 'rachel.kim@example.com',
                'relationship' => 'Friend',
                'years_known' => '5-10',
                'submitted_at' => null,
            ],
        ];

        foreach ($refs as $ref) {
            ReferenceRequest::create([
                'token' => Str::random(32),
                'caregiver_id' => $caregiver->id,
                'reference_name' => $ref['name'],
                'reference_email' => $ref['email'],
                'relationship' => $ref['relationship'],
                'years_known' => $ref['years_known'],
                'is_sponsor' => false,
                'rating_reliability' => $ref['rating_reliability'] ?? null,
                'rating_trustworthiness' => $ref['rating_trustworthiness'] ?? null,
                'rating_maturity' => $ref['rating_maturity'] ?? null,
                'rating_communication' => $ref['rating_communication'] ?? null,
                'rating_warmth' => $ref['rating_warmth'] ?? null,
                'rating_overall_recommendation' => $ref['rating_overall_recommendation'] ?? null,
                'strengths' => $ref['strengths'] ?? null,
                'concerns' => $ref['concerns'] ?? null,
                'additional_comments' => $ref['additional_comments'] ?? null,
                'submitted_at' => $ref['submitted_at'],
            ]);
        }

        $this->command->info('Applicant seeded: Sarah Johnson (sarah.johnson@example.com / password)');
    }
}
