<?php

namespace Database\Factories;

use App\Enums\CaregiverStatus;
use App\Models\AttributeDefinition;
use App\Models\Caregiver;
use App\Models\CertificationType;
use App\Models\Location;
use App\Models\SpecialtyType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Caregiver>
 */
class CaregiverFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $firstNames = [
            'Mary', 'Jennifer', 'Linda', 'Patricia', 'Jessica', 'Susan', 'Margaret', 'Dorothy',
            'Lisa', 'Nancy', 'Karen', 'Betty', 'Helen', 'Sandra', 'Donna', 'Carol',
            'Ruth', 'Sharon', 'Michelle', 'Laura', 'Sarah', 'Kimberly', 'Deborah', 'Stephanie',
            'Rebecca', 'Shirley', 'Cynthia', 'Angela', 'Melissa', 'Brenda', 'Amy', 'Anna',
            'Nicole', 'Emma', 'Madison', 'Olivia', 'Ava', 'Isabella', 'Mia', 'Charlotte',
            'Amelia', 'Harper', 'Evelyn', 'Abigail', 'Emily', 'Elizabeth', 'Sofia', 'Avery', 'Ella', 'Scarlett', 'Grace',
        ];

        $lastNames = [
            'Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis',
            'Rodriguez', 'Martinez', 'Hernandez', 'Lopez', 'Gonzalez', 'Wilson', 'Anderson', 'Thomas',
            'Taylor', 'Moore', 'Jackson', 'Martin', 'Lee', 'Perez', 'Thompson', 'White',
            'Harris', 'Sanchez', 'Clark', 'Ramirez', 'Lewis', 'Robinson', 'Walker', 'Young',
            'Allen', 'King', 'Wright', 'Scott', 'Torres', 'Nguyen', 'Hill', 'Flores',
            'Green', 'Adams', 'Nelson', 'Baker', 'Hall', 'Rivera', 'Campbell', 'Mitchell', 'Carter', 'Roberts',
        ];

        $streets = [
            'Main St', 'Oak Ave', 'Maple Dr', 'Cedar Ln', 'Pine St', 'Elm St', 'Park Ave',
            'Lake Dr', 'River Rd', 'Hill St', 'Forest Ave', 'Valley Dr', 'Sunset Blvd',
            'Highland Ave', 'Spring St', 'Church St', 'School Rd', 'Mill St', 'Center St',
        ];

        $cities = [
            'San Diego', 'La Jolla', 'Encinitas', 'Carlsbad', 'Oceanside', 'Escondido',
            'Vista', 'San Marcos', 'Solana Beach', 'Del Mar',
        ];

        $statusValues = array_column(CaregiverStatus::cases(), 'value');

        $firstName = $this->faker->randomElement($firstNames);
        $lastName = $this->faker->randomElement($lastNames);

        return [
            'user_id' => User::factory(['name' => $firstName.' '.$lastName, 'role' => 'caregiver']),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'slug' => Str::slug($firstName.' '.$lastName).'-'.$this->faker->numerify('#####'),
            'phone' => $this->faker->phoneNumber(),
            'address_line1' => $this->faker->numberBetween(100, 9999).' '.$this->faker->randomElement($streets),
            'address_line2' => null,
            'address_city' => $this->faker->randomElement($cities),
            'address_state' => 'CA',
            'address_zip' => $this->faker->numerify('92###'),
            'date_of_birth' => $this->faker->date('Y-m-d', '-18 years'),
            'biography' => $this->faker->optional()->paragraph(),
            'notes' => $this->faker->optional()->sentence(),
            'stripe_account_id' => null,
            'status' => $this->faker->randomElement($statusValues),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Caregiver $caregiver) {
            $specialtyIds = SpecialtyType::pluck('id')->toArray();
            $selectedSpecialties = $this->faker->randomElements($specialtyIds, $this->faker->numberBetween(1, 3));
            $caregiver->specialtyTypes()->sync($selectedSpecialties);

            $locationIds = Location::pluck('id')->toArray();
            $selectedLocations = $this->faker->randomElements($locationIds, $this->faker->numberBetween(1, 2));
            $locationSync = [];
            foreach ($selectedLocations as $locationId) {
                $locationSync[$locationId] = ['is_preferred' => $locationId === $selectedLocations[0]];
            }
            $caregiver->locations()->sync($locationSync);

            $attributeIds = AttributeDefinition::forCaregivers()->pluck('id')->toArray();
            $selectedAttributes = $this->faker->randomElements($attributeIds, $this->faker->numberBetween(1, 3));
            $attributeSync = [];
            foreach ($selectedAttributes as $attributeId) {
                $attributeSync[$attributeId] = ['value' => 'true'];
            }
            $caregiver->attributes()->sync($attributeSync);

            $certTypeIds = CertificationType::pluck('id')->toArray();
            $selectedCerts = $this->faker->randomElements($certTypeIds, $this->faker->numberBetween(2, 4));
            $certSync = [];
            foreach ($selectedCerts as $certTypeId) {
                $certSync[$certTypeId] = [
                    'expiration_date' => $this->faker->date('Y-m-d', '+2 years'),
                    'verified_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
                ];
            }
            $caregiver->certifications()->sync($certSync);
        });
    }
}
