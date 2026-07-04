<?php

namespace App\Enums;

enum ServiceType: string
{
    case Babysitter = 'babysitter';
    case Petsitter = 'petsitter';
    case CompanionCare = 'companion_care';
    case GroupChildcareInvoiced = 'group_childcare_invoiced';
    case CorporateInvoiced = 'corporate_invoiced';
    case Comped = 'comped';

    public function label(): string
    {
        return match ($this) {
            self::Babysitter => 'Babysitter',
            self::Petsitter => 'Petsitter',
            self::CompanionCare => 'Companion Care',
            self::GroupChildcareInvoiced => 'Group Childcare (Invoiced)',
            self::CorporateInvoiced => 'Corporate (Invoiced)',
            self::Comped => 'Comped',
        };
    }

    /**
     * Whether a booking of this service type must include at least one child.
     * Pet-only, companion care, and group childcare (tracked at the group level)
     * do not require a child.
     */
    public function requiresChild(): bool
    {
        return match ($this) {
            self::Petsitter, self::CompanionCare, self::GroupChildcareInvoiced => false,
            default => true,
        };
    }

    /**
     * Service type values that do not require a child on a booking.
     *
     * @return list<string>
     */
    public static function childExemptValues(): array
    {
        return array_values(array_map(
            fn (self $case): string => $case->value,
            array_filter(self::cases(), fn (self $case): bool => ! $case->requiresChild()),
        ));
    }
}
