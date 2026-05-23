<?php

namespace App\Enums;

enum CaregiverStatus: string
{
    case Applicant = 'applicant';
    case Active = 'active';
    case Inactive = 'inactive';
    case InProcess = 'in_process';
    case NonStarter = 'non_starter';
    case Fired = 'fired';
    case Ineligible = 'ineligible';
    case OnHold = 'on_hold';

    public function label(): string
    {
        return match ($this) {
            self::Applicant => 'Applicant',
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::InProcess => 'In Process',
            self::NonStarter => 'Non Starter',
            self::Fired => 'Fired',
            self::Ineligible => 'Ineligible',
            self::OnHold => 'On Hold',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Applicant => '#F48A91',
            self::Active => '#22C55E',
            self::Inactive => '#6B7280',
            self::InProcess => '#F59E0B',
            self::NonStarter => '#EF4444',
            self::Fired => '#DC2626',
            self::Ineligible => '#991B1B',
            self::OnHold => '#8B5CF6',
        };
    }
}
