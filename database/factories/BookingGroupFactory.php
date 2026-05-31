<?php

namespace Database\Factories;

use App\Enums\LocationType;
use App\Enums\ServiceType;
use App\Enums\SubmissionType;
use App\Models\BookingGroup;
use App\Models\Client;
use App\Models\ClientAddress;
use App\Models\Hotel;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookingGroupFactory extends Factory
{
    protected $model = BookingGroup::class;

    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'submitted_at' => now(),
            'submission_type' => SubmissionType::LoggedIn->value,
            'service_type' => ServiceType::Babysitter->value,
            'location_type' => LocationType::PrivateHome->value,
            'rental_platform' => null,
            'client_first_name' => fake()->firstName(),
            'client_last_name' => fake()->lastName(),
            'client_phone' => fake()->phoneNumber(),
            'client_email' => fake()->safeEmail(),
            'address_id' => ClientAddress::factory(),
            'address_line1' => fake()->streetAddress(),
            'address_line2' => null,
            'address_city' => fake()->city(),
            'address_state' => fake()->stateAbbr(),
            'address_zip' => fake()->postcode(),
            'hotel_id' => null,
            'hotel_name' => null,
            'children' => null,
            'pets' => null,
            'children_notes' => null,
            'sitter_preferences' => null,
            'other_adults_present' => null,
            'special_needs_notes' => null,
            'emergency_instructions' => null,
            'how_did_you_hear' => null,
            'caregiver_notes' => null,
            'notes_to_sitterwise' => null,
            'admin_notes' => null,
            'corporate_id' => null,
            'requires_payment' => true,
            'special_considerations' => null,
        ];
    }

    public function guest(): static
    {
        return $this->state(fn (array $attributes) => [
            'submission_type' => SubmissionType::Guest->value,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'submission_type' => SubmissionType::Admin->value,
        ]);
    }

    public function comped(): static
    {
        return $this->state(fn (array $attributes) => [
            'requires_payment' => false,
            'service_type' => ServiceType::Comped->value,
        ]);
    }

    public function hotel(): static
    {
        return $this->state(fn (array $attributes) => [
            'hotel_id' => Hotel::factory(),
            'location_type' => LocationType::Hotel->value,
        ]);
    }
}
