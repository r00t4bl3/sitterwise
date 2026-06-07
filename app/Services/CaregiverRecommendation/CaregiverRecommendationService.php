<?php

namespace App\Services\CaregiverRecommendation;

use App\Enums\CaregiverStatus;
use App\Enums\ServiceType;
use App\Models\Booking;
use App\Models\Caregiver;
use App\Models\Client;
use Illuminate\Support\Collection;

class CaregiverRecommendationService
{
    protected const TIER_LABELS = [
        1 => 'Previous Caregiver',
        2 => 'Excellent Match',
        3 => 'Good Match',
        4 => 'Fair Match',
        5 => 'Potential Match',
        6 => 'Available',
    ];

    protected const TIER_MATCH_ICONS = [
        1 => ['previous_work'],
        2 => ['available', 'specialty', 'location_preferred'],
        3 => ['specialty', 'location_willing'],
        4 => ['recent_work', 'specialty', 'location_preferred'],
        5 => ['recent_work'],
        6 => [],
    ];

    public function __construct(
        protected LocationMatcher $locationMatcher,
    ) {}

    /**
     * Get recommended caregivers for a client/booking using tiered ranking.
     *
     * Each caregiver appears in exactly one tier — the highest they qualify for.
     * All tiers enforce the default filter: active status, not blocked, not paused,
     * and has posted availability.
     */
    public function getRecommendedCaregivers(
        Client $client,
        ?Booking $booking = null,
        int $limit = 20
    ): Collection {
        $serviceType = $booking?->service_type;
        $startDate = $booking?->start_datetime;
        $endDate = $booking?->end_datetime;

        $bookingLocationId = $this->resolveBookingLocationId($booking);

        $allCaregivers = Caregiver::with([
            'certifications',
            'specialtyTypes',
            'availabilities',
            'locations',
        ])
            ->where('status', CaregiverStatus::Active->value)
            ->whereDoesntHave('blockedClients', fn ($q) => $q->where('client_id', $client->id))
            ->whereDoesntHave('activePause')
            ->has('availabilities')
            ->get();

        if ($allCaregivers->isEmpty()) {
            return collect();
        }

        $previousWorkCaregiverIds = $this->getPreviousWorkCaregiverIds($client);
        $recentWork3moIds = $this->getRecentWorkCaregiverIds(3);
        $recentWork6moIds = $this->getRecentWorkCaregiverIds(6);

        $scored = $allCaregivers->map(function (Caregiver $caregiver) use (
            $client,
            $booking,
            $serviceType,
            $startDate,
            $endDate,
            $bookingLocationId,
            $previousWorkCaregiverIds,
            $recentWork3moIds,
            $recentWork6moIds,
        ) {
            $attrs = $this->computeAttributes(
                $caregiver,
                $client,
                $serviceType,
                $startDate,
                $endDate,
                $bookingLocationId,
                $previousWorkCaregiverIds,
                $recentWork3moIds,
                $recentWork6moIds,
            );

            $tier = $this->assignTier($attrs);

            return [
                'id' => $caregiver->id,
                'name' => $caregiver->first_name.' '.$caregiver->last_name,
                'age' => $caregiver->date_of_birth?->age,
                'tier' => $tier,
                'tierLabel' => static::TIER_LABELS[$tier] ?? 'Available',
                'matchIcons' => static::TIER_MATCH_ICONS[$tier] ?? [],
                'hasBeenNotified' => $this->hasBeenNotified($caregiver, $booking),
            ];
        });

        return $scored
            ->sortBy([
                ['tier', 'asc'],
                ['name', 'asc'],
            ])
            ->take($limit)
            ->values();
    }

    /**
     * Resolve the booking's location area from the address city or hotel city.
     */
    protected function resolveBookingLocationId(?Booking $booking): ?int
    {
        if (! $booking) {
            return null;
        }

        $city = $this->locationMatcher->getBookingCity(
            $booking->address_city,
            $booking->relationLoaded('hotel') && $booking->hotel ? $booking->hotel->city : null,
        );

        if ($city === null && $booking->bookingGroup) {
            $city = $booking->bookingGroup->address_city;
        }

        return $this->locationMatcher->getLocationIdForCity($city);
    }

    /**
     * Compute boolean attributes for a caregiver relevant to tier assignment.
     */
    protected function computeAttributes(
        Caregiver $caregiver,
        Client $client,
        ?string $serviceType,
        mixed $startDate,
        mixed $endDate,
        ?int $bookingLocationId,
        Collection $previousWorkCaregiverIds,
        Collection $recentWork3moIds,
        Collection $recentWork6moIds,
    ): array {
        $previousWork = $previousWorkCaregiverIds->contains($caregiver->id);
        $available = $this->hasAvailabilityForBooking($caregiver, $startDate, $endDate);
        $specialty = $this->matchesServiceType($caregiver, $serviceType);
        $preferredLocation = $this->hasPreferredLocation($caregiver, $bookingLocationId);
        $willingLocation = $this->hasWillingLocation($caregiver, $bookingLocationId);
        $recentWork3mo = $recentWork3moIds->contains($caregiver->id);
        $recentWork6mo = $recentWork6moIds->contains($caregiver->id);

        return compact(
            'previousWork',
            'available',
            'specialty',
            'preferredLocation',
            'willingLocation',
            'recentWork3mo',
            'recentWork6mo',
        );
    }

    /**
     * Assign the highest tier a caregiver qualifies for.
     * Priority order: 1 (highest) → 6 (lowest).
     */
    protected function assignTier(array $attrs): int
    {
        // Tier 1: Previously worked with this client
        if ($attrs['previousWork']) {
            return 1;
        }

        // Tier 2: Available + specialty + preferred location
        if ($attrs['available'] && $attrs['specialty'] && $attrs['preferredLocation']) {
            return 2;
        }

        // Tier 3: Adjacent fit — willing to go there, not based there
        if ($attrs['specialty'] && $attrs['willingLocation'] && ! $attrs['preferredLocation']) {
            return 3;
        }

        // Tier 4: Recent work (3mo) + specialty + preferred location
        if ($attrs['recentWork3mo'] && $attrs['specialty'] && $attrs['preferredLocation']) {
            return 4;
        }

        // Tier 5: Recent work (6mo) + any fit (specialty or location)
        if ($attrs['recentWork6mo'] && ($attrs['specialty'] || $attrs['preferredLocation'] || $attrs['willingLocation'])) {
            return 5;
        }

        // Tier 6: All remaining (default filter already applied)
        return 6;
    }

    /**
     * Get IDs of caregivers who have worked with this client before.
     */
    protected function getPreviousWorkCaregiverIds(Client $client): Collection
    {
        return Booking::whereIn('status', ['completed', 'confirmed', 'paid'])
            ->whereNotNull('caregiver_id')
            ->whereHas('bookingGroup', fn ($q) => $q->where('client_id', $client->id))
            ->distinct()
            ->pluck('caregiver_id');
    }

    /**
     * Get IDs of caregivers who have completed work within the given number of months.
     */
    protected function getRecentWorkCaregiverIds(int $months): Collection
    {
        $since = now()->subMonths($months);

        return Booking::whereIn('status', ['completed', 'confirmed', 'paid'])
            ->where('start_datetime', '>=', $since)
            ->whereNotNull('caregiver_id')
            ->distinct()
            ->pluck('caregiver_id');
    }

    /**
     * Check if a caregiver has been notified about a booking.
     */
    protected function hasBeenNotified(Caregiver $caregiver, ?Booking $booking): bool
    {
        if (! $booking || ! $booking->exists) {
            return false;
        }

        return $caregiver->bookingNotifications()
            ->where('booking_id', $booking->id)
            ->exists();
    }

    /**
     * Check if caregiver has availability covering the booking dates/times.
     */
    protected function hasAvailabilityForBooking(
        Caregiver $caregiver,
        mixed $startDate,
        mixed $endDate
    ): bool {
        if (! $startDate || ! $endDate) {
            return false;
        }

        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);

        $bookingDates = [];
        $current = clone $start;
        while ($current <= $end) {
            $bookingDates[] = $current->format('Y-m-d');
            $current->modify('+1 day');
        }

        $availabilities = $caregiver->availabilities()
            ->where(function ($query) use ($bookingDates) {
                foreach ($bookingDates as $date) {
                    $query->orWhereDate('date', $date);
                }
            })
            ->get();

        if ($availabilities->isEmpty()) {
            return false;
        }

        $requiredSlotsPerDate = $this->getRequiredTimeSlots($startDate, $endDate);

        foreach ($bookingDates as $date) {
            $availability = $availabilities->first(function ($avail) use ($date) {
                if ($avail->date instanceof \DateTimeInterface) {
                    $availDate = $avail->date->format('Y-m-d');
                } else {
                    $availDate = date('Y-m-d', strtotime($avail->date));
                }

                return $availDate === $date;
            });

            if (! $availability || empty($availability->time_slots)) {
                return false;
            }

            $availableSlots = is_array($availability->time_slots)
                ? $availability->time_slots
                : json_decode($availability->time_slots, true);

            $requiredSlots = $requiredSlotsPerDate[$date] ?? [];

            if (empty($requiredSlots)) {
                continue;
            }

            $coveredSlots = array_intersect($requiredSlots, $availableSlots);

            if (count($coveredSlots) < count($requiredSlots)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine required time slots based on booking start/end times.
     */
    protected function getRequiredTimeSlots(string $startDate, string $endDate): array
    {
        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);

        $requiredSlots = [];

        $current = clone $start;
        while ($current <= $end) {
            $dateKey = $current->format('Y-m-d');
            $slots = [];

            $dayStart = ($current->format('Y-m-d') === $start->format('Y-m-d')) ? $start : new \DateTime($dateKey.' 00:00:00');
            $dayEnd = ($current->format('Y-m-d') === $end->format('Y-m-d')) ? $end : new \DateTime($dateKey.' 23:59:59');

            $morningStart = new \DateTime($dateKey.' 06:00:00');
            $morningEnd = new \DateTime($dateKey.' 12:00:00');
            $afternoonStart = new \DateTime($dateKey.' 12:00:00');
            $afternoonEnd = new \DateTime($dateKey.' 18:00:00');
            $eveningStart = new \DateTime($dateKey.' 18:00:00');
            $eveningEnd = new \DateTime($dateKey.' 23:00:00');

            if ($dayStart < $morningEnd && $dayEnd > $morningStart) {
                $slots[] = 'morning';
            }
            if ($dayStart < $afternoonEnd && $dayEnd > $afternoonStart) {
                $slots[] = 'afternoon';
            }
            if ($dayStart < $eveningEnd && $dayEnd > $eveningStart) {
                $slots[] = 'evening';
            }

            $requiredSlots[$dateKey] = $slots;
            $current->modify('+1 day');
        }

        return $requiredSlots;
    }

    /**
     * Check if caregiver's specialties match the service type.
     */
    protected function matchesServiceType(Caregiver $caregiver, ?string $serviceType): bool
    {
        if (! $serviceType) {
            return false;
        }

        $specialtyMap = [
            ServiceType::Babysitter->value => ['Babies', 'Toddlers', 'Preschool', 'School Age'],
            ServiceType::CompanionCare->value => ['Special Needs'],
            ServiceType::GroupChildcareInvoiced->value => ['Babies', 'Toddlers', 'Preschool', 'School Age'],
        ];

        $expectedSpecialties = $specialtyMap[$serviceType] ?? [];

        if (empty($expectedSpecialties)) {
            return false;
        }

        return $caregiver->specialtyTypes->pluck('name')->intersect($expectedSpecialties)->isNotEmpty();
    }

    /**
     * Check if caregiver has the booking area as a preferred location.
     */
    protected function hasPreferredLocation(Caregiver $caregiver, ?int $locationId): bool
    {
        if (! $locationId) {
            return false;
        }

        return $caregiver->locations
            ->where('id', $locationId)
            ->where('pivot.is_preferred', true)
            ->isNotEmpty();
    }

    /**
     * Check if caregiver has the booking area as a willing (non-preferred) location.
     */
    protected function hasWillingLocation(Caregiver $caregiver, ?int $locationId): bool
    {
        if (! $locationId) {
            return false;
        }

        return $caregiver->locations
            ->where('id', $locationId)
            ->where('pivot.is_preferred', false)
            ->isNotEmpty();
    }
}
