<?php

namespace Database\Seeders;

use App\Enums\BookingPaymentStatus;
use App\Enums\BookingStatus;
use App\Enums\LocationType;
use App\Enums\ServiceType;
use App\Models\Booking;
use App\Models\BookingCaregiverNotification;
use App\Models\BookingGroup;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Seeder;

class CaregiverBookingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Find the caregiver user
        $caregiverUser = User::where('email', 'caregiver@example.test')->first();

        if (! $caregiverUser || ! $caregiverUser->caregiver) {
            $this->command->error('Caregiver user not found. Run main seeder first.');

            return;
        }

        $caregiver = $caregiverUser->caregiver;

        // Find an existing client
        $client = Client::first();

        if (! $client) {
            $this->command->error('No clients found. Run main seeder first.');

            return;
        }

        // Create two available bookings for the caregiver
        $bookings = [
            [
                'start_datetime' => now()->addDays(2)->setHour(9)->setMinute(0),
                'end_datetime' => now()->addDays(2)->setHour(17)->setMinute(0),
                'service_type' => ServiceType::Babysitter->value,
                'location_type' => LocationType::PrivateHome->value,
                'address_line1' => '123 Main Street',
                'address_city' => 'San Diego',
                'address_state' => 'CA',
                'address_zip' => '92101',
            ],
            [
                'start_datetime' => now()->addDays(5)->setHour(10)->setMinute(0),
                'end_datetime' => now()->addDays(5)->setHour(18)->setMinute(0),
                'service_type' => ServiceType::Petsitter->value,
                'location_type' => LocationType::PrivateHome->value,
                'address_line1' => '456 Oak Avenue',
                'address_city' => 'La Jolla',
                'address_state' => 'CA',
                'address_zip' => '92037',
            ],
        ];

        foreach ($bookings as $bookingData) {
            $bookingGroup = BookingGroup::create([
                'client_id' => $client->id,
                'submitted_at' => now(),
                'submission_type' => 'admin',
                'is_split' => false,
            ]);

            $booking = Booking::create([
                'booking_group_id' => $bookingGroup->id,
                'client_id' => $client->id,
                'caregiver_id' => $caregiver->id,
                'client_first_name' => $client->first_name,
                'client_last_name' => $client->last_name,
                'client_email' => $client->user->email,
                'client_phone' => $client->phone,
                'special_considerations' => fake()->word(),
                'caregiver_notes' => fake()->sentence(),
                'status' => BookingStatus::Received->value,
                'payment_status' => BookingPaymentStatus::Pending->value,
                'total_amount' => rand(100, 300),
                'paid_to_caregiver' => rand(50, 100),
                'requires_payment' => true,
                'children' => $client->children->map(fn ($child) => [
                    'name' => $child->name,
                    'gender' => $child->gender,
                    'birth_month' => $child->birth_month,
                    'birth_year' => $child->birth_year,
                ])->toArray(),
                'pets' => $client->pets->map(fn ($pet) => [
                    'name' => $pet->name,
                    'type' => $pet->type,
                    'breed' => $pet->breed,
                    'notes' => $pet->notes,
                ])->toArray(),
                ...$bookingData,
            ]);

            // Create notification record for the caregiver
            BookingCaregiverNotification::create([
                'booking_id' => $booking->id,
                'caregiver_id' => $caregiver->id,
                'notified_at' => now(),
                'viewed_at' => null,
                'responded_at' => null,
                'claimed' => false,
            ]);

            $this->command->info("Created booking #{$booking->id} for caregiver@example.test");
        }
    }
}
