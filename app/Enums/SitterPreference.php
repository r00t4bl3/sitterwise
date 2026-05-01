<?php

namespace App\Enums;

enum SitterPreference: string
{
    // case CollegeAged = 'college_aged';
    // case Seasoned = 'seasoned';
    case BabySpecialist = 'baby_specialist';
    case SpecialNeedsExp = 'special_needs_exp';
    case WillingToSwim = 'willing_to_swim';
    case ChildIsSick = 'child_is_sick';

    public function label(): string
    {
        return match ($this) {
            // self::CollegeAged => 'College Aged',
            // self::Seasoned => 'Seasoned',
            self::BabySpecialist => 'Baby Specialist',
            self::SpecialNeedsExp => 'Special Needs Experience',
            self::WillingToSwim => 'Willing to Swim',
            self::ChildIsSick => 'Child is Sick',
        };
    }
}
