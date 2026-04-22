<?php

namespace Database\Seeders;

use App\Models\PricingRule;
use Illuminate\Database\Seeder;

class PricingRulesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Babysitter
        foreach ([1, 2] as $children) {
            PricingRule::create([
                'service_type' => 'babysitter',
                'number_of_children' => $children,
                'is_for_pets' => false,
                'charge_to_client' => 35.00,
                'paid_to_caregiver' => 23.00,
                'payment_form' => 'Stripe',
                'sitterwise_cut' => 12.00,
            ]);
        }
        foreach ([3, 4] as $children) {
            PricingRule::create([
                'service_type' => 'babysitter',
                'number_of_children' => $children,
                'is_for_pets' => false,
                'charge_to_client' => 40.00,
                'paid_to_caregiver' => 28.00,
                'payment_form' => 'Stripe',
                'sitterwise_cut' => 12.00,
            ]);
        }

        // Petsitter
        PricingRule::create([
            'service_type' => 'petsitter',
            'number_of_children' => null,
            'is_for_pets' => true,
            'charge_to_client' => 30.00,
            'paid_to_caregiver' => 23.00,
            'payment_form' => 'Stripe',
            'sitterwise_cut' => 7.00,
        ]);

        // Companion Care
        foreach ([1, 2] as $children) {
            PricingRule::create([
                'service_type' => 'companion_care',
                'number_of_children' => $children,
                'is_for_pets' => false,
                'charge_to_client' => 35.00,
                'paid_to_caregiver' => 23.00,
                'payment_form' => 'Stripe',
                'sitterwise_cut' => 12.00,
            ]);
        }

        // Group Childcare (Invoiced)
        PricingRule::create([
            'service_type' => 'group_childcare_invoiced',
            'number_of_children' => 5,
            'is_for_pets' => false,
            'charge_to_client' => 36.00,
            'charge_to_client_notes' => 'typically, but could can change based on the job . . . this is invoiced, so we take care of this at the end of the month and send a bill to the client',
            'paid_to_caregiver' => 23.00,
            'payment_form' => 'OnPay (Payroll)',
            'sitterwise_cut' => 0.00, // Implied from image
        ]);

        // Corporate (Invoiced)
        foreach ([1, 2] as $children) {
            PricingRule::create([
                'service_type' => 'corporate_invoiced',
                'number_of_children' => $children,
                'is_for_pets' => false,
                'charge_to_client' => 36.00,
                'charge_to_client_notes' => 'typically, but could can change based on the job . . . this is invoiced, so we take care of this at the end of the month and send a bill to the client',
                'paid_to_caregiver' => 23.00,
                'payment_form' => 'OnPay (Payroll)',
                'sitterwise_cut' => 0.00, // Implied from image
            ]);
        }

        // Need to ask if this is still valid, but this is what we have in the image
        // // Overnight Newborn Care
        // PricingRule::create([
        //     'service_type' => 'overnight_newborn_care',
        //     'number_of_children' => 1,
        //     'is_for_pets' => false,
        //     'charge_to_client' => 35.00,
        //     'paid_to_caregiver' => 28.00,
        //     'payment_form' => 'Stripe',
        //     'sitterwise_cut' => 7.00,
        // ]);

        // Comped
        foreach ([1, 2] as $children) {
            PricingRule::create([
                'service_type' => 'comped',
                'number_of_children' => $children,
                'is_for_pets' => false,
                'charge_to_client' => 0.00,
                'paid_to_caregiver' => 23.00,
                'payment_form' => 'Stripe',
                'sitterwise_cut' => 0.00,
            ]);
        }
        foreach ([3, 4] as $children) {
            PricingRule::create([
                'service_type' => 'comped',
                'number_of_children' => $children,
                'is_for_pets' => false,
                'charge_to_client' => 0.00,
                'paid_to_caregiver' => 28.00,
                'payment_form' => 'Stripe',
                'sitterwise_cut' => 0.00,
            ]);
        }
    }
}
