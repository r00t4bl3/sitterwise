<?php

namespace App\Enums;

enum CaregiverStatus: string
{
    case Applicant = 'applicant';
    case UnderReview = 'under_review';
    case InterviewScheduled = 'interview_scheduled';
    case BackgroundCheck = 'background_check';
    case HiredOnboarding = 'hired_onboarding';
    case Active = 'active';
    case Inactive = 'inactive';
    case NonStarter = 'non_starter';
    case Fired = 'fired';
    case Ineligible = 'ineligible';
    case OnHold = 'on_hold';

    public function label(): string
    {
        return match ($this) {
            self::Applicant => 'Applicant',
            self::UnderReview => 'Under Review',
            self::InterviewScheduled => 'Interview Scheduled',
            self::BackgroundCheck => 'Background Check',
            self::HiredOnboarding => 'Hired / Onboarding',
            self::Active => 'Active',
            self::Inactive => 'Inactive',
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
            self::UnderReview => '#F59E0B',
            self::InterviewScheduled => '#8B5CF6',
            self::BackgroundCheck => '#3B82F6',
            self::HiredOnboarding => '#0EA5E9',
            self::Active => '#22C55E',
            self::Inactive => '#6B7280',
            self::NonStarter => '#EF4444',
            self::Fired => '#DC2626',
            self::Ineligible => '#991B1B',
            self::OnHold => '#8B5CF6',
        };
    }

    public static function terminal(): array
    {
        return [self::Active, self::Inactive, self::NonStarter, self::Fired, self::Ineligible];
    }

    public static function toArray(): array
    {
        return array_map(fn (self $case) => [
            'value' => $case->value,
            'label' => $case->label(),
            'color' => $case->color(),
            'is_terminal' => in_array($case, self::terminal()),
        ], self::cases());
    }
}
