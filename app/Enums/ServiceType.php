<?php
namespace App\Enums;

enum ServiceType: string {
    case Babysitter             = 'babysitter';
    case Petsitter              = 'petsitter';
    case CompanionCare          = 'companion_care';
    case GroupChildcareInvoiced = 'group_childcare_invoiced';
    case CorporateInvoiced      = 'corporate_invoiced';
    case Comped                 = 'comped';

    public function label(): string
    {
        return match ($this) {
            self::Babysitter             => 'Babysitter',
            self::Petsitter              => 'Petsitter',
            self::CompanionCare          => 'Companion Care',
            self::GroupChildcareInvoiced => 'Group Childcare (Invoiced)',
            self::CorporateInvoiced      => 'Corporate (Invoiced)',
            self::Comped                 => 'Comped',
        };
    }
}