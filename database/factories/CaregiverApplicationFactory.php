<?php

namespace Database\Factories;

use App\Models\Caregiver;
use App\Models\CaregiverApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CaregiverApplication>
 */
class CaregiverApplicationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'caregiver_id' => Caregiver::factory(),
            'data' => [
                'sponsor' => [
                    'first_name' => fake()->firstName(),
                    'last_name' => fake()->lastName(),
                    'email' => fake()->unique()->safeEmail(),
                    'phone' => fake()->phoneNumber(),
                    'relationship' => fake()->randomElement(['Friend', 'Former Employer', 'Colleague', 'Neighbor']),
                ],
                'personal' => [
                    'first_name' => fake()->firstName(),
                    'last_name' => fake()->lastName(),
                    'address' => fake()->address(),
                    'phone' => fake()->phoneNumber(),
                    'dob' => fake()->date('Y-m-d', '-18 years'),
                ],
                'position' => [
                    'babysitting' => true,
                    'petsitting' => fake()->boolean(),
                    'group_events' => fake()->boolean(),
                ],
                'availability' => [
                    'weekday_mornings' => fake()->boolean(),
                    'weekday_afternoons' => fake()->boolean(),
                    'weekday_evenings' => fake()->boolean(),
                    'weekends' => true,
                    'overnights' => fake()->boolean(),
                    'notes' => fake()->optional()->sentence(),
                ],
                'education' => [
                    'level' => fake()->randomElement(['high_school', 'associate', 'bachelor', 'master', 'phd']),
                    'college' => fake()->optional()->company(),
                    'graduation_year' => (string) fake()->year(),
                    'degree' => fake()->optional()->word(),
                ],
                'experiences' => [
                    [
                        'start_month' => fake()->date('Y-m', '-5 years'),
                        'end_month' => fake()->date('Y-m', '-1 year'),
                        'role' => fake()->randomElement(['Nanny', 'Babysitter', 'After-School Care', 'Daycare Worker']),
                        'organization' => fake()->company(),
                        'description' => fake()->optional()->sentence(),
                        'ages_served' => fake()->randomElements(['babies', 'toddlers', 'preschool', 'school_age'], 2),
                    ],
                ],
                'certifications' => [],
                'skills' => [
                    'special_needs' => fake()->boolean(),
                    'work_from_home' => fake()->boolean(),
                    'swimming' => fake()->boolean(),
                    'driving' => true,
                    'other' => fake()->optional()->sentence(),
                ],
                'references' => [
                    [
                        'name' => fake()->name(),
                        'email' => fake()->unique()->safeEmail(),
                        'phone' => fake()->optional()->phoneNumber(),
                        'relationship' => fake()->randomElement(['Friend', 'Former Employer', 'Co-worker', 'Neighbor']),
                        'years_known' => fake()->randomElement(['<1', '1-3', '3-5', '5-10', '10+']),
                    ],
                    [
                        'name' => fake()->name(),
                        'email' => fake()->unique()->safeEmail(),
                        'phone' => fake()->optional()->phoneNumber(),
                        'relationship' => fake()->randomElement(['Friend', 'Former Employer', 'Co-worker', 'Neighbor']),
                        'years_known' => fake()->randomElement(['<1', '1-3', '3-5', '5-10', '10+']),
                    ],
                    [
                        'name' => fake()->name(),
                        'email' => fake()->unique()->safeEmail(),
                        'phone' => fake()->optional()->phoneNumber(),
                        'relationship' => fake()->randomElement(['Friend', 'Former Employer', 'Co-worker', 'Neighbor']),
                        'years_known' => fake()->randomElement(['<1', '1-3', '3-5', '5-10', '10+']),
                    ],
                ],
                'location' => [
                    'north_county' => fake()->boolean(),
                    'south_east_county' => fake()->boolean(),
                    'flexible' => true,
                ],
                'age_groups' => [
                    'babies' => fake()->boolean(),
                    'toddlers' => true,
                    'preschool' => true,
                    'school_age' => fake()->boolean(),
                ],
                'terms' => [
                    'agree' => true,
                ],
                'verification' => [
                    'signature' => fake()->name(),
                    'agree' => true,
                ],
                'agreement' => [
                    'signature' => fake()->name(),
                    'agree' => true,
                ],
            ],
            'submitted_at' => now(),
        ];
    }

    public function withFullData(): static
    {
        return $this->state(fn (array $attributes) => [
            'data' => [
                'sponsor' => [
                    'first_name' => 'Sponsor',
                    'last_name' => 'Reference',
                    'email' => 'sponsor@example.com',
                    'phone' => '555-1234',
                    'relationship' => 'Friend',
                ],
                'personal' => [
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'address' => '123 Main St, San Diego, CA 92101',
                    'phone' => '555-5678',
                    'dob' => '1990-01-01',
                ],
                'position' => [
                    'babysitting' => true,
                    'petsitting' => false,
                    'group_events' => false,
                ],
                'availability' => [
                    'weekday_mornings' => true,
                    'weekday_afternoons' => false,
                    'weekday_evenings' => false,
                    'weekends' => true,
                    'overnights' => false,
                    'notes' => 'Flexible schedule',
                ],
                'education' => [
                    'level' => 'bachelor',
                    'college' => 'State University',
                    'graduation_year' => '2015',
                    'degree' => 'Early Childhood Education',
                ],
                'experiences' => [
                    [
                        'start_month' => '2020-01',
                        'end_month' => '2022-12',
                        'role' => 'Nanny',
                        'organization' => 'Smith Family',
                        'description' => 'Cared for 2 children ages 2 and 5',
                        'ages_served' => ['toddlers', 'preschool'],
                    ],
                ],
                'certifications' => [],
                'skills' => [
                    'special_needs' => false,
                    'work_from_home' => false,
                    'swimming' => true,
                    'driving' => true,
                    'other' => 'CPR certified',
                ],
                'references' => [
                    [
                        'name' => 'Reference One',
                        'email' => 'ref1@example.com',
                        'phone' => '555-0001',
                        'relationship' => 'Former Employer',
                        'years_known' => '3-5',
                    ],
                    [
                        'name' => 'Reference Two',
                        'email' => 'ref2@example.com',
                        'phone' => '555-0002',
                        'relationship' => 'Friend',
                        'years_known' => '5-10',
                    ],
                    [
                        'name' => 'Reference Three',
                        'email' => 'ref3@example.com',
                        'phone' => '555-0003',
                        'relationship' => 'Co-worker',
                        'years_known' => '1-3',
                    ],
                ],
                'location' => [
                    'north_county' => true,
                    'south_east_county' => false,
                    'flexible' => true,
                ],
                'age_groups' => [
                    'babies' => false,
                    'toddlers' => true,
                    'preschool' => true,
                    'school_age' => false,
                ],
                'terms' => [
                    'agree' => true,
                ],
                'verification' => [
                    'signature' => 'John Doe',
                    'agree' => true,
                ],
                'agreement' => [
                    'signature' => 'John Doe',
                    'agree' => true,
                ],
            ],
        ]);
    }
}
