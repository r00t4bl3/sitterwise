<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\CaregiverStatus;
use App\Models\Booking;
use App\Models\Caregiver;
use App\Support\BusinessTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class CaregiverBadgeService
{
    private const TRUSTLINE_CERTIFICATION_TYPE_ID = 3;

    /**
     * @return list<array{
     *     slug: string,
     *     name: string,
     *     group: string,
     *     tier: 'teal'|'coral'|'navy',
     *     variant: string,
     *     earned: bool,
     *     earned_date: string|null,
     *     criteria: string,
     *     progress: string|null,
     * }>
     */
    public function badgesFor(Caregiver $caregiver): array
    {
        $completedBookings = $this->getCompletedBookings($caregiver);
        $completedCount = $completedBookings->count();
        $completedBookingsByDate = $completedBookings->sortBy('end_datetime')->values();

        $fiveStarCount = $this->getFiveStarReviewCount($caregiver);
        $fiveStarReviewsByDate = $this->getFiveStarReviewsByDate($caregiver);

        $trustlineCertified = $caregiver->certifications()
            ->where('certification_type_id', self::TRUSTLINE_CERTIFICATION_TYPE_ID)
            ->wherePivot('verified_at', '!=', null)
            ->exists();

        $lifesaverCount = $this->getLifesaverCount($caregiver);
        // Note: lifesaver date tracking requires an explicit lifesaver flag on bookings

        $hotelCount = $this->countByLocationType($completedBookings, 'hotel');
        $eventCount = $this->countByLocationType($completedBookings, 'event_venue');
        $daytimeCount = $this->countByTimeWindow($completedBookings, 8, 16);
        $infantCount = $this->countWithChildrenUnder2($completedBookings);

        $memberSince = $this->getMemberSince($caregiver);
        $tenureYears = $memberSince->diffInYears(now());

        $onboardingComplete = $this->hasCompletedOnboarding($caregiver);

        return [
            // Getting Started
            $this->makeBadge(
                slug: 'ready-set-sit',
                name: 'Ready, Set, Sit',
                group: 'Getting Started',
                tier: 'teal',
                variant: 'checklist',
                earned: $onboardingComplete,
                earnedDate: null,
                criteria: 'Complete the onboarding checklist and training quiz',
                progress: $onboardingComplete ? null : 'In progress',
            ),

            // Jobs Completed
            $this->makeBadge(
                slug: 'first-day',
                name: 'First Day',
                group: 'Jobs Completed',
                tier: 'teal',
                variant: 'star',
                earned: $completedCount >= 1,
                earnedDate: $completedCount >= 1 ? $this->formatDate($completedBookingsByDate->first()?->end_datetime) : null,
                criteria: 'Complete your first job',
                progress: null,
            ),
            $this->makeBadge(
                slug: 'trustline-ten',
                name: 'TrustLine Ten',
                group: 'Jobs Completed',
                tier: 'teal',
                variant: 'shield',
                earned: $completedCount >= 10,
                earnedDate: $completedCount >= 10 ? $this->formatDate($completedBookingsByDate->get(9)?->end_datetime) : null,
                criteria: '10 jobs — TrustLine reimbursement unlocked',
                progress: $completedCount >= 10 ? null : "{$completedCount} of 10",
            ),
            $this->makeBadge(
                slug: 'twenty-five-club',
                name: 'Twenty-Five Club',
                group: 'Jobs Completed',
                tier: 'coral',
                variant: 'number',
                earned: $completedCount >= 25,
                earnedDate: $completedCount >= 25 ? $this->formatDate($completedBookingsByDate->get(24)?->end_datetime) : null,
                criteria: '25 jobs completed',
                progress: $completedCount >= 25 ? null : "{$completedCount} of 25",
            ),
            $this->makeBadge(
                slug: 'fifty-strong',
                name: 'Fifty Strong',
                group: 'Jobs Completed',
                tier: 'navy',
                variant: 'number',
                earned: $completedCount >= 50,
                earnedDate: $completedCount >= 50 ? $this->formatDate($completedBookingsByDate->get(49)?->end_datetime) : null,
                criteria: '50 jobs completed',
                progress: $completedCount >= 50 ? null : "{$completedCount} of 50",
            ),
            $this->makeBadge(
                slug: 'century-sitter',
                name: 'Century Sitter',
                group: 'Jobs Completed',
                tier: 'navy',
                variant: 'number',
                earned: $completedCount >= 100,
                earnedDate: $completedCount >= 100 ? $this->formatDate($completedBookingsByDate->get(99)?->end_datetime) : null,
                criteria: '100 jobs completed',
                progress: $completedCount >= 100 ? null : "{$completedCount} of 100",
            ),
            $this->makeBadge(
                slug: 'the-marion',
                name: 'The Marion',
                group: 'Jobs Completed',
                tier: 'navy',
                variant: 'number',
                earned: $completedCount >= 250,
                earnedDate: $completedCount >= 250 ? $this->formatDate($completedBookingsByDate->get(249)?->end_datetime) : null,
                criteria: '250 jobs — named for our founder',
                progress: $completedCount >= 250 ? null : "{$completedCount} of 250",
            ),

            // Lifesavers
            $this->makeBadge(
                slug: 'lifesaver',
                name: 'Lifesaver',
                group: 'Lifesavers',
                tier: 'coral',
                variant: 'crosshair',
                earned: $lifesaverCount >= 5,
                earnedDate: null,
                criteria: '5 Lifesaver jobs completed',
                progress: $lifesaverCount >= 5 ? null : "{$lifesaverCount} of 5",
            ),
            $this->makeBadge(
                slug: 'first-responder',
                name: 'First Responder',
                group: 'Lifesavers',
                tier: 'navy',
                variant: 'crosshair',
                earned: $lifesaverCount >= 10,
                earnedDate: null,
                criteria: '10 Lifesaver jobs completed',
                progress: $lifesaverCount >= 10 ? null : "{$lifesaverCount} of 10",
            ),
            $this->makeBadge(
                slug: 'guardian-angel',
                name: 'Guardian Angel',
                group: 'Lifesavers',
                tier: 'navy',
                variant: 'crosshair',
                earned: $lifesaverCount >= 25,
                earnedDate: null,
                criteria: '25 Lifesaver jobs completed',
                progress: $lifesaverCount >= 25 ? null : "{$lifesaverCount} of 25",
            ),

            // Specialties
            $this->makeBadge(
                slug: 'daymaker',
                name: 'The Daymaker',
                group: 'Specialties',
                tier: 'teal',
                variant: 'sun',
                earned: $daytimeCount >= 10,
                earnedDate: null,
                criteria: '10 daytime jobs completed (the hard-to-fill 8 AM – 4 PM window)',
                progress: $daytimeCount >= 10 ? null : "{$daytimeCount} of 10",
            ),
            $this->makeBadge(
                slug: 'hotel-pro',
                name: 'Hotel Pro',
                group: 'Specialties',
                tier: 'coral',
                variant: 'building',
                earned: $hotelCount >= 10,
                earnedDate: null,
                criteria: '10 hotel bookings completed',
                progress: $hotelCount >= 10 ? null : "{$hotelCount} of 10",
            ),
            $this->makeBadge(
                slug: 'event-ace',
                name: 'Event Ace',
                group: 'Specialties',
                tier: 'teal',
                variant: 'sparkles',
                earned: $eventCount >= 5,
                earnedDate: null,
                criteria: '5 event-childcare jobs completed',
                progress: $eventCount >= 5 ? null : "{$eventCount} of 5",
            ),
            $this->makeBadge(
                slug: 'infant-specialist',
                name: 'Infant Specialist',
                group: 'Specialties',
                tier: 'teal',
                variant: 'heart',
                earned: $infantCount >= 10,
                earnedDate: null,
                criteria: '10 jobs caring for children under 2',
                progress: $infantCount >= 10 ? null : "{$infantCount} of 10",
            ),

            // Five-Star Service
            $this->makeBadge(
                slug: 'family-favorite',
                name: 'Family Favorite',
                group: 'Five-Star Service',
                tier: 'teal',
                variant: 'star-filled',
                earned: $fiveStarCount >= 5,
                earnedDate: $fiveStarCount >= 5 ? $this->formatDate($fiveStarReviewsByDate->get(4)?->created_at) : null,
                criteria: '5 five-star reviews',
                progress: $fiveStarCount >= 5 ? null : "{$fiveStarCount} of 5",
            ),
            $this->makeBadge(
                slug: 'beloved',
                name: 'Beloved',
                group: 'Five-Star Service',
                tier: 'coral',
                variant: 'star-filled',
                earned: $fiveStarCount >= 10,
                earnedDate: $fiveStarCount >= 10 ? $this->formatDate($fiveStarReviewsByDate->get(9)?->created_at) : null,
                criteria: '10 five-star reviews',
                progress: $fiveStarCount >= 10 ? null : "{$fiveStarCount} of 10",
            ),
            $this->makeBadge(
                slug: 'legendary',
                name: 'Legendary',
                group: 'Five-Star Service',
                tier: 'navy',
                variant: 'star-filled',
                earned: $fiveStarCount >= 25,
                earnedDate: $fiveStarCount >= 25 ? $this->formatDate($fiveStarReviewsByDate->get(24)?->created_at) : null,
                criteria: '25 five-star reviews',
                progress: $fiveStarCount >= 25 ? null : "{$fiveStarCount} of 25",
            ),

            // Sitterwise Family
            $this->makeBadge(
                slug: 'one-year-in',
                name: 'One Year In',
                group: 'Sitterwise Family',
                tier: 'teal',
                variant: 'cake',
                earned: $tenureYears >= 1,
                earnedDate: $tenureYears >= 1 ? $this->formatDate($memberSince->addYear()) : null,
                criteria: '1 year with Sitterwise',
                progress: null,
            ),
            $this->makeBadge(
                slug: 'three-and-thriving',
                name: 'Three & Thriving',
                group: 'Sitterwise Family',
                tier: 'navy',
                variant: 'cake',
                earned: $tenureYears >= 3,
                earnedDate: $tenureYears >= 3 ? $this->formatDate($memberSince->addYears(3)) : null,
                criteria: '3 years with Sitterwise',
                progress: null,
            ),
            $this->makeBadge(
                slug: 'heart-of-the-house',
                name: 'Heart of the House',
                group: 'Sitterwise Family',
                tier: 'navy',
                variant: 'cake',
                earned: $tenureYears >= 5,
                earnedDate: $tenureYears >= 5 ? $this->formatDate($memberSince->addYears(5)) : null,
                criteria: '5 years with Sitterwise',
                progress: null,
            ),
        ];
    }

    /**
     * Whether the caregiver has finished onboarding, for the "Ready, Set, Sit"
     * badge. Active caregivers were onboarded (migrated accounts predate the
     * checklist), otherwise every onboarding checklist item must be completed.
     */
    private function hasCompletedOnboarding(Caregiver $caregiver): bool
    {
        if ($caregiver->status === CaregiverStatus::Active) {
            return true;
        }

        return $caregiver->onboardingChecklistItems()->exists()
            && $caregiver->onboardingChecklistItems()->whereNull('completed_at')->doesntExist();
    }

    /**
     * The caregiver's true start with Sitterwise, for tenure badges.
     *
     * Migrated accounts carry an import date in `created_at`, so tenure is
     * anchored on the earliest of `created_at` and their first booking — the
     * same real job history the "First Day" badge relies on.
     */
    private function getMemberSince(Caregiver $caregiver): CarbonImmutable
    {
        $created = $caregiver->created_at;
        $earliestBooking = $caregiver->bookings()->min('start_datetime');

        if ($earliestBooking === null) {
            return $created;
        }

        $earliest = CarbonImmutable::parse($earliestBooking);

        return $earliest->lessThan($created) ? $earliest : $created;
    }

    /**
     * @return Collection<int, Booking>
     */
    private function getCompletedBookings(Caregiver $caregiver): Collection
    {
        return $caregiver->bookings()
            ->with('bookingGroup')
            ->whereIn('status', [BookingStatus::Completed->value, BookingStatus::Paid->value])
            ->get();
    }

    private function getFiveStarReviewCount(Caregiver $caregiver): int
    {
        return $caregiver->ratings()
            ->where('rating', '>=', 5)
            ->count();
    }

    private function getFiveStarReviewsByDate(Caregiver $caregiver): Collection
    {
        return $caregiver->ratings()
            ->where('rating', '>=', 5)
            ->orderBy('created_at')
            ->get();
    }

    private function getLifesaverCount(Caregiver $caregiver): int
    {
        $lifesaver = app(LifesaverService::class);

        return $this->getCompletedBookings($caregiver)
            ->filter(fn (Booking $booking) => $lifesaver->wasLifesaverRescue($booking))
            ->count();
    }

    /**
     * @param  Collection<int, Booking>  $bookings
     */
    private function countByLocationType(Collection $bookings, string $locationType): int
    {
        return $bookings->filter(fn (Booking $booking) => $booking->bookingGroup?->location_type === $locationType)->count();
    }

    /**
     * @param  Collection<int, Booking>  $bookings
     */
    private function countByTimeWindow(Collection $bookings, int $startHour, int $endHour): int
    {
        return $bookings->filter(function (Booking $booking) use ($startHour, $endHour) {
            // start/end are stored in UTC; the window is expressed in local
            // (business) time, so compare against the Pacific wall-clock hour.
            $start = $booking->start_datetime?->setTimezone(BusinessTime::TZ);
            $end = $booking->end_datetime?->setTimezone(BusinessTime::TZ);

            if ($start === null || $end === null) {
                return false;
            }

            return $start->hour >= $startHour && $end->hour <= $endHour;
        })->count();
    }

    /**
     * @param  Collection<int, Booking>  $bookings
     */
    private function countWithChildrenUnder2(Collection $bookings): int
    {
        return $bookings->filter(function (Booking $booking) {
            $children = $booking->bookingGroup?->children;

            if (empty($children)) {
                return false;
            }

            foreach ($children as $child) {
                $birthYear = $child['birth_year'] ?? null;
                $birthMonth = $child['birth_month'] ?? null;

                if ($birthYear === null) {
                    continue;
                }

                $birthdate = CarbonImmutable::createFromDate((int) $birthYear, (int) ($birthMonth ?? 1), 1);
                $age = $birthdate->diffInYears(CarbonImmutable::now());

                if ($age >= 0 && $age < 2) {
                    return true;
                }
            }

            return false;
        })->count();
    }

    private function formatDate(?CarbonImmutable $date): ?string
    {
        return $date?->format('M j, Y');
    }

    /**
     * @param  'teal'|'coral'|'navy'  $tier
     * @return array{
     *     slug: string,
     *     name: string,
     *     group: string,
     *     tier: 'teal'|'coral'|'navy',
     *     variant: string,
     *     earned: bool,
     *     earned_date: string|null,
     *     criteria: string,
     *     progress: string|null,
     * }
     */
    private function makeBadge(
        string $slug,
        string $name,
        string $group,
        string $tier,
        string $variant,
        bool $earned,
        ?string $earnedDate,
        string $criteria,
        ?string $progress,
    ): array {
        return [
            'slug' => $slug,
            'name' => $name,
            'group' => $group,
            'tier' => $tier,
            'variant' => $variant,
            'earned' => $earned,
            'earned_date' => $earnedDate,
            'criteria' => $criteria,
            'progress' => $progress,
        ];
    }
}
