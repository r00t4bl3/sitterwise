<?php

use App\Enums\SitterPreference;
use App\Enums\SpecialConsideration;
use App\Models\Booking;
use App\Models\BookingGroup;

describe('SitterPreference toSpecialConsideration mapping', function () {
    it('maps BabySpecialist to InfantCare', function () {
        $result = SitterPreference::BabySpecialist->toSpecialConsideration();
        expect($result)->toBe(SpecialConsideration::InfantCare);
    });

    it('maps SpecialNeedsCare to SpecialNeedsCare', function () {
        $result = SitterPreference::SpecialNeedsCare->toSpecialConsideration();
        expect($result)->toBe(SpecialConsideration::SpecialNeedsCare);
    });

    it('maps WillingToSwim to SwimmingRequested', function () {
        $result = SitterPreference::WillingToSwim->toSpecialConsideration();
        expect($result)->toBe(SpecialConsideration::SwimmingRequested);
    });

    it('maps ChildIsSick to ChildIsSick', function () {
        $result = SitterPreference::ChildIsSick->toSpecialConsideration();
        expect($result)->toBe(SpecialConsideration::ChildIsSick);
    });
});

describe('BookingGroup calculateSpecialConsiderations', function () {
    it('calculates InfantCare from BabySpecialist sitter preference', function () {
        $bookingGroup = new BookingGroup;
        $bookingGroup->sitter_preferences = ['baby_specialist'];
        $bookingGroup->pets = [];
        $bookingGroup->other_adults_present = null;

        $booking = new Booking;
        $booking->setRelation('bookingGroup', $bookingGroup);

        $bookingGroup->calculateSpecialConsiderations();

        expect($booking->special_considerations)->toContain('infant_care');
        expect($booking->special_considerations)->toHaveCount(1);
    });

    it('calculates SpecialNeedsCare from SpecialNeedsCare sitter preference', function () {
        $bookingGroup = new BookingGroup;
        $bookingGroup->sitter_preferences = ['special_needs_care'];
        $bookingGroup->pets = [];
        $bookingGroup->other_adults_present = null;

        $booking = new Booking;
        $booking->setRelation('bookingGroup', $bookingGroup);

        $bookingGroup->calculateSpecialConsiderations();

        expect($booking->special_considerations)->toContain('special_needs_care');
        expect($booking->special_considerations)->toHaveCount(1);
    });

    it('calculates SwimmingRequested from WillingToSwim sitter preference', function () {
        $bookingGroup = new BookingGroup;
        $bookingGroup->sitter_preferences = ['willing_to_swim'];
        $bookingGroup->pets = [];
        $bookingGroup->other_adults_present = null;

        $booking = new Booking;
        $booking->setRelation('bookingGroup', $bookingGroup);

        $bookingGroup->calculateSpecialConsiderations();

        expect($booking->special_considerations)->toContain('swimming_requested');
        expect($booking->special_considerations)->toHaveCount(1);
    });

    it('calculates ChildIsSick from ChildIsSick sitter preference', function () {
        $bookingGroup = new BookingGroup;
        $bookingGroup->sitter_preferences = ['child_is_sick'];
        $bookingGroup->pets = [];
        $bookingGroup->other_adults_present = null;

        $booking = new Booking;
        $booking->setRelation('bookingGroup', $bookingGroup);

        $bookingGroup->calculateSpecialConsiderations();

        expect($booking->special_considerations)->toContain('child_is_sick');
        expect($booking->special_considerations)->toHaveCount(1);
    });

    it('calculates FamilyHasDogsOnsite from dog pet', function () {
        $bookingGroup = new BookingGroup;
        $bookingGroup->sitter_preferences = [];
        $bookingGroup->pets = [['name' => 'Buddy', 'type' => 'dog', 'breed' => 'Golden Retriever']];
        $bookingGroup->other_adults_present = null;

        $booking = new Booking;
        $booking->setRelation('bookingGroup', $bookingGroup);

        $bookingGroup->calculateSpecialConsiderations();

        expect($booking->special_considerations)->toContain('family_has_dogs_onsite');
        expect($booking->special_considerations)->toHaveCount(1);
    });

    it('calculates FamilyHasCatsOnsite from cat pet', function () {
        $bookingGroup = new BookingGroup;
        $bookingGroup->sitter_preferences = [];
        $bookingGroup->pets = [['name' => 'Whiskers', 'type' => 'cat', 'breed' => 'Persian']];
        $bookingGroup->other_adults_present = null;

        $booking = new Booking;
        $booking->setRelation('bookingGroup', $bookingGroup);

        $bookingGroup->calculateSpecialConsiderations();

        expect($booking->special_considerations)->toContain('family_has_cats_onsite');
        expect($booking->special_considerations)->toHaveCount(1);
    });

    it('calculates ParentWillBePresent from other_adults_present', function () {
        $bookingGroup = new BookingGroup;
        $bookingGroup->sitter_preferences = [];
        $bookingGroup->pets = [];
        $bookingGroup->other_adults_present = 'Grandparents';

        $booking = new Booking;
        $booking->setRelation('bookingGroup', $bookingGroup);

        $bookingGroup->calculateSpecialConsiderations();

        expect($booking->special_considerations)->toContain('parent_will_be_present');
        expect($booking->special_considerations)->toHaveCount(1);
    });

    it('calculates multiple considerations from multiple sitter preferences', function () {
        $bookingGroup = new BookingGroup;
        $bookingGroup->sitter_preferences = ['baby_specialist', 'willing_to_swim'];
        $bookingGroup->pets = [];
        $bookingGroup->other_adults_present = null;

        $booking = new Booking;
        $booking->setRelation('bookingGroup', $bookingGroup);

        $bookingGroup->calculateSpecialConsiderations();

        expect($booking->special_considerations)->toContain('infant_care');
        expect($booking->special_considerations)->toContain('swimming_requested');
        expect($booking->special_considerations)->toHaveCount(2);
    });

    it('calculates all 7 considerations when all inputs are present', function () {
        $bookingGroup = new BookingGroup;
        $bookingGroup->sitter_preferences = ['baby_specialist', 'special_needs_care', 'willing_to_swim', 'child_is_sick'];
        $bookingGroup->pets = [
            ['name' => 'Buddy', 'type' => 'dog'],
            ['name' => 'Whiskers', 'type' => 'cat'],
        ];
        $bookingGroup->other_adults_present = 'Parent';

        $booking = new Booking;
        $booking->setRelation('bookingGroup', $bookingGroup);

        $bookingGroup->calculateSpecialConsiderations();

        expect($booking->special_considerations)->toContain('infant_care');
        expect($booking->special_considerations)->toContain('special_needs_care');
        expect($booking->special_considerations)->toContain('swimming_requested');
        expect($booking->special_considerations)->toContain('child_is_sick');
        expect($booking->special_considerations)->toContain('family_has_dogs_onsite');
        expect($booking->special_considerations)->toContain('family_has_cats_onsite');
        expect($booking->special_considerations)->toContain('parent_will_be_present');
        expect($booking->special_considerations)->toHaveCount(7);
    });

    it('deduplicates considerations', function () {
        $bookingGroup = new BookingGroup;
        $bookingGroup->sitter_preferences = ['baby_specialist'];
        $bookingGroup->pets = [
            ['name' => 'Buddy', 'type' => 'dog'],
            ['name' => 'Max', 'type' => 'dog'],
        ];
        $bookingGroup->other_adults_present = 'Parent';

        $booking = new Booking;
        $booking->setRelation('bookingGroup', $bookingGroup);

        $bookingGroup->calculateSpecialConsiderations();

        expect($booking->special_considerations)->toContain('infant_care');
        expect($booking->special_considerations)->toContain('family_has_dogs_onsite');
        expect($booking->special_considerations)->toContain('parent_will_be_present');
        expect($booking->special_considerations)->toHaveCount(3);
    });

    it('returns empty array when no inputs trigger considerations', function () {
        $bookingGroup = new BookingGroup;
        $bookingGroup->sitter_preferences = [];
        $bookingGroup->pets = [];
        $bookingGroup->other_adults_present = null;

        $booking = new Booking;
        $booking->setRelation('bookingGroup', $bookingGroup);

        $bookingGroup->calculateSpecialConsiderations();

        expect($booking->special_considerations)->toBe([]);
    });

    it('handles case-insensitive pet types', function () {
        $bookingGroup = new BookingGroup;
        $bookingGroup->sitter_preferences = [];
        $bookingGroup->pets = [
            ['name' => 'Buddy', 'type' => 'Dog'],
            ['name' => 'Whiskers', 'type' => 'CAT'],
        ];
        $bookingGroup->other_adults_present = null;

        $booking = new Booking;
        $booking->setRelation('bookingGroup', $bookingGroup);

        $bookingGroup->calculateSpecialConsiderations();

        expect($booking->special_considerations)->toContain('family_has_dogs_onsite');
        expect($booking->special_considerations)->toContain('family_has_cats_onsite');
        expect($booking->special_considerations)->toHaveCount(2);
    });

    it('ignores non-dog and non-cat pet types', function () {
        $bookingGroup = new BookingGroup;
        $bookingGroup->sitter_preferences = [];
        $bookingGroup->pets = [
            ['name' => 'Nemo', 'type' => 'fish'],
            ['name' => 'Tweety', 'type' => 'bird'],
        ];
        $bookingGroup->other_adults_present = null;

        $booking = new Booking;
        $booking->setRelation('bookingGroup', $bookingGroup);

        $bookingGroup->calculateSpecialConsiderations();

        expect($booking->special_considerations)->toBe([]);
    });

    it('handles null pets gracefully', function () {
        $bookingGroup = new BookingGroup;
        $bookingGroup->sitter_preferences = ['baby_specialist'];
        $bookingGroup->pets = null;
        $bookingGroup->other_adults_present = null;

        $booking = new Booking;
        $booking->setRelation('bookingGroup', $bookingGroup);

        $bookingGroup->calculateSpecialConsiderations();

        expect($booking->special_considerations)->toContain('infant_care');
        expect($booking->special_considerations)->toHaveCount(1);
    });

    it('handles null sitter_preferences gracefully', function () {
        $bookingGroup = new BookingGroup;
        $bookingGroup->sitter_preferences = null;
        $bookingGroup->pets = [['name' => 'Buddy', 'type' => 'dog']];
        $bookingGroup->other_adults_present = null;

        $booking = new Booking;
        $booking->setRelation('bookingGroup', $bookingGroup);

        $bookingGroup->calculateSpecialConsiderations();

        expect($booking->special_considerations)->toContain('family_has_dogs_onsite');
        expect($booking->special_considerations)->toHaveCount(1);
    });

    it('ignores empty string other_adults_present', function () {
        $bookingGroup = new BookingGroup;
        $bookingGroup->sitter_preferences = [];
        $bookingGroup->pets = [];
        $bookingGroup->other_adults_present = '';

        $booking = new Booking;
        $booking->setRelation('bookingGroup', $bookingGroup);

        $bookingGroup->calculateSpecialConsiderations();

        expect($booking->special_considerations)->toBe([]);
    });

    it('calculates ParentWillBePresent for any non-empty other_adults_present', function () {
        $bookingGroup = new BookingGroup;
        $bookingGroup->sitter_preferences = [];
        $bookingGroup->pets = [];
        $bookingGroup->other_adults_present = '1';

        $booking = new Booking;
        $booking->setRelation('bookingGroup', $bookingGroup);

        $bookingGroup->calculateSpecialConsiderations();

        expect($booking->special_considerations)->toContain('parent_will_be_present');
        expect($booking->special_considerations)->toHaveCount(1);
    });

    it('maps all 4 sitter preferences to correct special considerations', function () {
        foreach (SitterPreference::cases() as $preference) {
            $bookingGroup = new BookingGroup;
            $bookingGroup->sitter_preferences = [$preference->value];
            $bookingGroup->pets = [];
            $bookingGroup->other_adults_present = null;

            $booking = new Booking;
            $booking->setRelation('bookingGroup', $bookingGroup);

            $bookingGroup->calculateSpecialConsiderations();

            expect($booking->special_considerations)->toContain(
                $preference->toSpecialConsideration()->value
            );
        }
    });
});
