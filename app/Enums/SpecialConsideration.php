<?php

namespace App\Enums;

enum SpecialConsideration: string
{
    case InfantCare = 'infant_care';
    case SpecialNeedsCare = 'special_needs_care';
    case FamilyHasDogsOnsite = 'family_has_dogs_onsite';
    case FamilyHasCatsOnsite = 'family_has_cats_onsite';
    case ParentWillBePresent = 'parent_will_be_present';
    case SwimmingRequested = 'swimming_requested';
    case ChildIsSick = 'child_is_sick';

    public function label(): string
    {
        return match ($this) {
            self::InfantCare => 'Infant Care (under 12 months)',
            self::SpecialNeedsCare => 'Special Needs Care',
            self::FamilyHasDogsOnsite => 'Family has Dog(s) Onsite',
            self::FamilyHasCatsOnsite => 'Family has Cat(s) Onsite',
            self::ParentWillBePresent => 'Parent will be present',
            self::SwimmingRequested => 'Swimming Requested',
            self::ChildIsSick => 'Child is Sick',
        };
    }
}
