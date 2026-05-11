<?php

namespace Database\Seeders;

use App\Models\QuickLink;
use Illuminate\Database\Seeder;

class QuickLinkSeeder extends Seeder
{
    public function run(): void
    {
        $links = [
            [
                'title' => 'Caregiver FAQ',
                'url' => 'https://sitterwise.com/caregiver-faq/',
                'description' => 'Sitterwise FAQ',
                'icon' => 'Link',
                'sort_order' => 0,
                'is_active' => true,
                'is_external' => true,
            ],
            [
                'title' => 'CPR/First Aid',
                'url' => 'https://nationalcprfoundation.com/courses/standard-cpr-aed-first-aid/',
                'description' => 'CPR or First Aid certification',
                'icon' => 'Link',
                'sort_order' => 1,
                'is_active' => true,
                'is_external' => true,
            ],
            [
                'title' => 'Caregiver Guidelines',
                'url' => 'https://sitterwise.com/caregiver-guidelines/',
                'description' => 'Caregiver Guideline',
                'icon' => 'Link',
                'sort_order' => 1,
                'is_active' => true,
                'is_external' => true,
            ],
            [
                'title' => 'Add Concierge to MailChimp',
                'url' => 'https://sitterwise.com/concierge-mailchimp/',
                'description' => 'Add a concierge or hotel contact to our MailChimp mailing list.',
                'icon' => 'Link',
                'sort_order' => 0,
                'is_active' => true,
                'is_external' => true,
            ],
            [
                'title' => 'Add New Caregiver to MailChimp',
                'url' => 'https://sitterwise.com/caregiver-mailchimp/',
                'description' => 'Add a new caregiver to our MailChimp mailing list so that she receives the new hire email sequence.',
                'icon' => 'Link',
                'sort_order' => 0,
                'is_active' => true,
                'is_external' => true,
            ],
            [
                'title' => 'Reference Form',
                'url' => 'https://sitterwise.com/reference/',
                'description' => 'To send to caregiver references.',
                'icon' => 'Link',
                'sort_order' => 0,
                'is_active' => true,
                'is_external' => true,
            ],
            [
                'title' => 'Reference Tracker',
                'url' => 'https://docs.google.com/spreadsheets/d/1g4Lj0iH10YShgF94X2-71njj4QFgQY2A8kMizL9neNo/edit?gid=1257437773#gid=1257437773',
                'description' => 'Keep track of references and scheduled interviews.',
                'icon' => 'Link',
                'sort_order' => 0,
                'is_active' => true,
                'is_external' => true,
            ],
            [
                'title' => 'Toy Bag',
                'url' => 'https://sitterwise.com/toy-bag',
                'description' => 'Toys and games suggested for hotel and private residence jobs.',
                'icon' => 'Link',
                'sort_order' => 1,
                'is_active' => true,
                'is_external' => true,
            ],
            [
                'title' => 'Hotel Parking',
                'url' => 'https://sitterwise.com/hotel-parking',
                'description' => 'Parking directions for caregivers.',
                'icon' => 'Link',
                'sort_order' => 1,
                'is_active' => true,
                'is_external' => true,
            ],
            [
                'title' => 'Care.com Caregiver Update',
                'url' => 'https://sitterwise.com/care-update',
                'description' => 'Update the Care.com system with caregiver background checks and CPR/First Aid certifications. Add new caregivers to their system.',
                'icon' => 'Link',
                'sort_order' => 1,
                'is_active' => true,
                'is_external' => true,
            ],
            [
                'title' => 'Caregiver Update Form',
                'url' => 'https://sitterwise.com/caregiver-update',
                'description' => 'Caregivers can use this form to update their CPR/First Aid and various other information.',
                'icon' => 'Link',
                'sort_order' => 1,
                'is_active' => true,
                'is_external' => true,
            ],
            [
                'title' => 'CC Authorization',
                'url' => 'https://sitterwise.com/ccauthorization',
                'description' => "When a client's credit card does not go through, we use this form to request new card information.",
                'icon' => 'Link',
                'sort_order' => 1,
                'is_active' => true,
                'is_external' => true,
            ],
            [
                'title' => 'San Diego Hotels Map',
                'url' => 'https://earth.google.com/earth/d/1_TkXfWEQsLNnOj_nCWif7pn_JCrOykMI?usp=sharing',
                'description' => "Amy's map of our usual hotels.",
                'icon' => 'Link',
                'sort_order' => 0,
                'is_active' => true,
                'is_external' => true,
            ],
            [
                'title' => 'Dashboard',
                'url' => '/dashboard',
                'description' => 'View your dashboard',
                'icon' => 'home',
                'sort_order' => 1,
                'is_active' => true,
                'is_external' => false,
            ],
            [
                'title' => 'Book a Sitter',
                'url' => '/bookings/create',
                'description' => 'Book a new sitter',
                'icon' => 'calendar',
                'sort_order' => 2,
                'is_active' => true,
                'is_external' => false,
            ],
            [
                'title' => 'Caregivers',
                'url' => '/caregivers',
                'description' => 'Browse available caregivers',
                'icon' => 'users',
                'sort_order' => 3,
                'is_active' => true,
                'is_external' => false,
            ],
            [
                'title' => 'Help Center',
                'url' => 'https://help.sitterwise.com',
                'description' => 'Get help and support',
                'icon' => 'help-circle',
                'sort_order' => 4,
                'is_active' => true,
                'is_external' => true,
            ],
        ];

        foreach ($links as $link) {
            QuickLink::create($link);
        }
    }
}
