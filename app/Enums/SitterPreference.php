<?php

namespace App\Enums;

enum SitterPreference: string
{
    case BabySpecialist = 'baby_specialist';
    case SpecialNeedsCare = 'special_needs_care';
    case WillingToSwim = 'willing_to_swim';
    case ChildIsSick = 'child_is_sick';

    public function label(): string
    {
        return match ($this) {
            self::BabySpecialist => 'Baby Specialist',
            self::SpecialNeedsCare => 'Special Needs Care',
            self::WillingToSwim => 'Willing to Swim',
            self::ChildIsSick => 'Child is Sick',
        };
    }

    public function toSpecialConsideration(): SpecialConsideration
    {
        return match ($this) {
            self::BabySpecialist => SpecialConsideration::InfantCare,
            self::SpecialNeedsCare => SpecialConsideration::SpecialNeedsCare,
            self::WillingToSwim => SpecialConsideration::SwimmingRequested,
            self::ChildIsSick => SpecialConsideration::ChildIsSick,
        };
    }
}
