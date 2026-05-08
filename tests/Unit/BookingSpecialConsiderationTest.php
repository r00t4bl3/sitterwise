<?php

use App\Enums\SitterPreference;
use App\Enums\SpecialConsideration;
use App\Models\Booking;

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

describe('Booking calculateSpecialConsiderations', function () {
    it('calculates InfantCare from BabySpecialist sitter preference', function () {
        $booking = new Booking;
        $booking->sitter_preferences = ['baby_specialist'];
        $booking->pets = [];
        $booking->other_adults_present = null;

        $booking->calculateSpecialConsiderations();

        expect($booking->special_considerations)->toContain('infant_care');
        expect($booking->special_considerations)->toHaveCount(1);
    });

    it('calculates SpecialNeedsCare from SpecialNeedsCare sitter preference', function () {
        $booking = new Booking;
        $booking->sitter_preferences = ['special_needs_care'];
        $booking->pets = [];
        $booking->other_adults_present = null;

        $booking->calculateSpecialConsiderations();

        expect($booking->special_considerations)->toContain('special_needs_care');
        expect($booking->special_considerations)->toHaveCount(1);
    });

    it('calculates SwimmingRequested from WillingToSwim sitter preference', function () {
        $booking = new Booking;
        $booking->sitter_preferences = ['willing_to_swim'];
        $booking->pets = [];
        $booking->other_adults_present = null;

        $booking->calculateSpecialConsiderations();

        expect($booking->special_considerations)->toContain('swimming_requested');
        expect($booking->special_considerations)->toHaveCount(1);
    });

    it('calculates ChildIsSick from ChildIsSick sitter preference', function () {
        $booking = new Booking;
        $booking->sitter_preferences = ['child_is_sick'];
        $booking->pets = [];
        $booking->other_adults_present = null;

        $booking->calculateSpecialConsiderations();

        expect($booking->special_considerations)->toContain('child_is_sick');
        expect($booking->special_considerations)->toHaveCount(1);
    });

    it('calculates FamilyHasDogsOnsite from dog pet', function () {
        $booking = new Booking;
        $booking->sitter_preferences = [];
        $booking->pets = [['name' => 'Buddy', 'type' => 'dog', 'breed' => 'Golden Retriever']];
        $booking->other_adults_present = null;

        $booking->calculateSpecialConsiderations();

        expect($booking->special_considerations)->toContain('family_has_dogs_onsite');
        expect($booking->special_considerations)->toHaveCount(1);
    });

    it('calculates FamilyHasCatsOnsite from cat pet', function () {
        $booking = new Booking;
        $booking->sitter_preferences = [];
        $booking->pets = [['name' => 'Whiskers', 'type' => 'cat', 'breed' => 'Persian']];
        $booking->other_adults_present = null;

        $booking->calculateSpecialConsiderations();

        expect($booking->special_considerations)->toContain('family_has_cats_onsite');
        expect($booking->special_considerations)->toHaveCount(1);
    });

    it('calculates ParentWillBePresent from other_adults_present', function () {
        $booking = new Booking;
        $booking->sitter_preferences = [];
        $booking->pets = [];
        $booking->other_adults_present = 'Grandparents';

        $booking->calculateSpecialConsiderations();

        expect($booking->special_considerations)->toContain('parent_will_be_present');
        expect($booking->special_considerations)->toHaveCount(1);
    });

    it('calculates multiple considerations from multiple sitter preferences', function () {
        $booking = new Booking;
        $booking->sitter_preferences = ['baby_specialist', 'willing_to_swim'];
        $booking->pets = [];
        $booking->other_adults_present = null;

        $booking->calculateSpecialConsiderations();

        expect($booking->special_considerations)->toContain('infant_care');
        expect($booking->special_considerations)->toContain('swimming_requested');
        expect($booking->special_considerations)->toHaveCount(2);
    });

    it('calculates all 7 considerations when all inputs are present', function () {
        $booking = new Booking;
        $booking->sitter_preferences = ['baby_specialist', 'special_needs_care', 'willing_to_swim', 'child_is_sick'];
        $booking->pets = [
            ['name' => 'Buddy', 'type' => 'dog'],
            ['name' => 'Whiskers', 'type' => 'cat'],
        ];
        $booking->other_adults_present = 'Parent';

        $booking->calculateSpecialConsiderations();

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
        $booking = new Booking;
        $booking->sitter_preferences = ['baby_specialist'];
        $booking->pets = [
            ['name' => 'Buddy', 'type' => 'dog'],
            ['name' => 'Max', 'type' => 'dog'],
        ];
        $booking->other_adults_present = 'Parent';

        $booking->calculateSpecialConsiderations();

        expect($booking->special_considerations)->toContain('infant_care');
        expect($booking->special_considerations)->toContain('family_has_dogs_onsite');
        expect($booking->special_considerations)->toContain('parent_will_be_present');
        expect($booking->special_considerations)->toHaveCount(3);
    });

    it('returns empty array when no inputs trigger considerations', function () {
        $booking = new Booking;
        $booking->sitter_preferences = [];
        $booking->pets = [];
        $booking->other_adults_present = null;

        $booking->calculateSpecialConsiderations();

        expect($booking->special_considerations)->toBe([]);
    });

    it('handles case-insensitive pet types', function () {
        $booking = new Booking;
        $booking->sitter_preferences = [];
        $booking->pets = [
            ['name' => 'Buddy', 'type' => 'Dog'],
            ['name' => 'Whiskers', 'type' => 'CAT'],
        ];
        $booking->other_adults_present = null;

        $booking->calculateSpecialConsiderations();

        expect($booking->special_considerations)->toContain('family_has_dogs_onsite');
        expect($booking->special_considerations)->toContain('family_has_cats_onsite');
        expect($booking->special_considerations)->toHaveCount(2);
    });

    it('ignores non-dog and non-cat pet types', function () {
        $booking = new Booking;
        $booking->sitter_preferences = [];
        $booking->pets = [
            ['name' => 'Nemo', 'type' => 'fish'],
            ['name' => 'Tweety', 'type' => 'bird'],
        ];
        $booking->other_adults_present = null;

        $booking->calculateSpecialConsiderations();

        expect($booking->special_considerations)->toBe([]);
    });

    it('handles null pets gracefully', function () {
        $booking = new Booking;
        $booking->sitter_preferences = ['baby_specialist'];
        $booking->pets = null;
        $booking->other_adults_present = null;

        $booking->calculateSpecialConsiderations();

        expect($booking->special_considerations)->toContain('infant_care');
        expect($booking->special_considerations)->toHaveCount(1);
    });

    it('handles null sitter_preferences gracefully', function () {
        $booking = new Booking;
        $booking->sitter_preferences = null;
        $booking->pets = [['name' => 'Buddy', 'type' => 'dog']];
        $booking->other_adults_present = null;

        $booking->calculateSpecialConsiderations();

        expect($booking->special_considerations)->toContain('family_has_dogs_onsite');
        expect($booking->special_considerations)->toHaveCount(1);
    });

    it('ignores empty string other_adults_present', function () {
        $booking = new Booking;
        $booking->sitter_preferences = [];
        $booking->pets = [];
        $booking->other_adults_present = '';

        $booking->calculateSpecialConsiderations();

        expect($booking->special_considerations)->toBe([]);
    });

    it('calculates ParentWillBePresent for any non-empty other_adults_present', function () {
        $booking = new Booking;
        $booking->sitter_preferences = [];
        $booking->pets = [];
        $booking->other_adults_present = '1';

        $booking->calculateSpecialConsiderations();

        expect($booking->special_considerations)->toContain('parent_will_be_present');
        expect($booking->special_considerations)->toHaveCount(1);
    });

    it('maps all 4 sitter preferences to correct special considerations', function () {
        foreach (SitterPreference::cases() as $preference) {
            $booking = new Booking;
            $booking->sitter_preferences = [$preference->value];
            $booking->pets = [];
            $booking->other_adults_present = null;

            $booking->calculateSpecialConsiderations();

            expect($booking->special_considerations)->toContain(
                $preference->toSpecialConsideration()->value
            );
        }
    });
});
