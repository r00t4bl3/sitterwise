<?php

namespace App\Services\CaregiverRecommendation;

use App\Enums\BookingStatus;
use App\Enums\CaregiverStatus;
use App\Enums\ServiceType;
use App\Enums\SitterPreference;
use App\Models\Booking;
use App\Models\Caregiver;
use App\Models\Client;
use Illuminate\Support\Collection;

class CaregiverRecommendationService
{
    protected const SCORE_WEIGHTS = [
        'available_and_favorited' => 100000,
        'available' => 10000,
        'specialty' => 1000,
        'preferred_location' => 100,
        'willing_location' => 10,
        'recent_work_3mo' => 3,
        'previous_work' => 2,
        'recent_work_6mo' => 1,
    ];

    public function __construct(
        protected LocationMatcher $locationMatcher,
    ) {}

    /**
     * Get recommended caregivers for a client/booking using weighted scoring.
     *
     * Each caregiver is scored based on matched criteria (availability, specialty,
     * location fit, previous work, favorited). Sorted by score descending.
     * All results enforce the default filter: active status, not blocked, not paused,
     * and has posted availability.
     */
    public function getRecommendedCaregivers(
        Client $client,
        ?Booking $booking = null,
        int $limit = 1000,
        array $dateRanges = [],
    ): Collection {
        $serviceType = $booking?->service_type;

        if (empty($dateRanges) && $booking?->start_datetime && $booking?->end_datetime) {
            $dateRanges = [
                ['start' => $booking->start_datetime, 'end' => $booking->end_datetime],
            ];
        }

        $bookingLocationId = $this->resolveBookingLocationId($booking);

        $sitterPreferences = $booking?->sitter_preferences ?? $client->sitter_preferences ?? [];
        $favoriteCaregiverIds = $client->relationLoaded('favoriteCaregivers')
            ? $client->favoriteCaregivers->pluck('id')
            : $client->favoriteCaregivers()->pluck('caregivers.id');

        $allCaregivers = Caregiver::with([
            'certifications',
            'specialtyTypes',
            'availabilities.usedSlots',
            'locations',
            'attributes',
        ])
            ->where('status', CaregiverStatus::Active->value)
            ->whereDoesntHave('blockedClients', fn ($q) => $q->where('client_id', $client->id))
            ->whereDoesntHave('activePause')
            ->get();

        if ($allCaregivers->isEmpty()) {
            return collect();
        }

        $bufferMinutes = (int) config('caregiver.buffer_minutes');

        $bookingDatesForBuffer = [];
        if (! empty($dateRanges)) {
            foreach ($dateRanges as $range) {
                $start = new \DateTime($range['start']);
                $end = new \DateTime($range['end']);
                $current = clone $start;
                while ($current <= $end) {
                    $bookingDatesForBuffer[] = $current->format('Y-m-d');
                    $current->modify('+1 day');
                }
            }
            $bookingDatesForBuffer = array_unique($bookingDatesForBuffer);
        }

        $existingBookingsByCaregiver = collect();
        if (! empty($bookingDatesForBuffer)) {
            $caregiverIds = $allCaregivers->pluck('id');

            $existingBookingsByCaregiver = Booking::whereIn('caregiver_id', $caregiverIds)
                ->whereIn('status', [BookingStatus::Confirmed->value, BookingStatus::Received->value])
                ->when($booking, fn ($q) => $q->where('id', '!=', $booking->id))
                ->where(function ($q) use ($bookingDatesForBuffer) {
                    foreach ($bookingDatesForBuffer as $date) {
                        $q->orWhere(fn ($sub) => $sub
                            ->whereDate('start_datetime', '<=', $date)
                            ->whereDate('end_datetime', '>=', $date)
                        );
                    }
                })
                ->orderBy('start_datetime')
                ->get()
                ->groupBy('caregiver_id');
        }

        $previousWorkCaregiverIds = $this->getPreviousWorkCaregiverIds($client);
        $recentWork3moIds = $this->getRecentWorkCaregiverIds(3);
        $recentWork6moIds = $this->getRecentWorkCaregiverIds(6);

        $scored = $allCaregivers->map(function (Caregiver $caregiver) use (
            $client,
            $booking,
            $serviceType,
            $dateRanges,
            $bookingLocationId,
            $previousWorkCaregiverIds,
            $recentWork3moIds,
            $recentWork6moIds,
            $sitterPreferences,
            $favoriteCaregiverIds,
            $existingBookingsByCaregiver,
            $bufferMinutes,
        ) {
            $attrs = $this->computeAttributes(
                $caregiver,
                $client,
                $serviceType,
                $dateRanges,
                $bookingLocationId,
                $previousWorkCaregiverIds,
                $recentWork3moIds,
                $recentWork6moIds,
                $sitterPreferences,
                $favoriteCaregiverIds,
                $existingBookingsByCaregiver,
                $bufferMinutes,
            );

            $score = $this->computeScore($attrs);

            return [
                'id' => $caregiver->id,
                'name' => $caregiver->first_name.' '.$caregiver->last_name,
                'age' => $caregiver->date_of_birth?->age,
                'score' => $score,
                'matchIcons' => $this->getMatchIcons($attrs),
                'hasBeenNotified' => $this->hasBeenNotified($caregiver, $booking),
            ];
        });

        return $scored
            ->sortBy([
                ['score', 'desc'],
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
     * Compute boolean attributes for a caregiver relevant to scoring.
     */
    protected function computeAttributes(
        Caregiver $caregiver,
        Client $client,
        ?string $serviceType,
        array $dateRanges,
        ?int $bookingLocationId,
        Collection $previousWorkCaregiverIds,
        Collection $recentWork3moIds,
        Collection $recentWork6moIds,
        array $sitterPreferences = [],
        Collection $favoriteCaregiverIds = new Collection,
        Collection $existingBookings = new Collection,
        int $bufferMinutes = 0,
    ): array {
        $previousWork = $previousWorkCaregiverIds->contains($caregiver->id);
        $available = $this->hasAvailabilityForBooking($caregiver, $dateRanges, $existingBookings, $bufferMinutes);
        $specialty = $this->matchesServiceType($caregiver, $serviceType, $sitterPreferences)
            || $this->matchesSitterPreferences($caregiver, $sitterPreferences);
        $preferredLocation = $this->hasPreferredLocation($caregiver, $bookingLocationId);
        $willingLocation = $this->hasWillingLocation($caregiver, $bookingLocationId);
        $recentWork3mo = $recentWork3moIds->contains($caregiver->id);
        $recentWork6mo = $recentWork6moIds->contains($caregiver->id);
        $favorited = $favoriteCaregiverIds->contains($caregiver->id);

        return compact(
            'previousWork',
            'available',
            'specialty',
            'preferredLocation',
            'willingLocation',
            'recentWork3mo',
            'recentWork6mo',
            'favorited',
        );
    }

    /**
     * Compute a weighted score for a caregiver based on matched attributes.
     */
    protected function computeScore(array $attrs): int
    {
        $score = 0;

        if ($attrs['available'] && $attrs['favorited']) {
            $score += static::SCORE_WEIGHTS['available_and_favorited'];
        }

        if ($attrs['available']) {
            $score += static::SCORE_WEIGHTS['available'];
        }

        if ($attrs['specialty']) {
            $score += static::SCORE_WEIGHTS['specialty'];
        }

        if ($attrs['preferredLocation']) {
            $score += static::SCORE_WEIGHTS['preferred_location'];
        }

        if ($attrs['willingLocation']) {
            $score += static::SCORE_WEIGHTS['willing_location'];
        }

        if ($attrs['recentWork3mo']) {
            $score += static::SCORE_WEIGHTS['recent_work_3mo'];
        }

        if ($attrs['previousWork']) {
            $score += static::SCORE_WEIGHTS['previous_work'];
        }

        if ($attrs['recentWork6mo']) {
            $score += static::SCORE_WEIGHTS['recent_work_6mo'];
        }

        return $score;
    }

    /**
     * Build match icons array from matched attributes.
     */
    protected function getMatchIcons(array $attrs): array
    {
        $icons = [];

        if ($attrs['favorited']) {
            $icons[] = 'favorited';
        }

        if ($attrs['available']) {
            $icons[] = 'available';
        }

        if ($attrs['specialty']) {
            $icons[] = 'specialty';
        }

        if ($attrs['preferredLocation']) {
            $icons[] = 'location_preferred';
        }

        if ($attrs['willingLocation']) {
            $icons[] = 'location_willing';
        }

        if ($attrs['recentWork3mo'] || $attrs['recentWork6mo']) {
            $icons[] = 'recent_work';
        }

        if ($attrs['previousWork']) {
            $icons[] = 'previous_work';
        }

        return $icons;
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
     * Check if caregiver has availability covering all of the given date ranges.
     *
     * Each range is an associative array with 'start' and 'end' keys (DateTime|string).
     * All unique dates across all ranges are checked — the caregiver must have
     * availability for every required date.
     */
    protected function hasAvailabilityForBooking(
        Caregiver $caregiver,
        array $dateRanges,
        Collection $existingBookings = new Collection,
        int $bufferMinutes = 0,
    ): bool {
        if (empty($dateRanges)) {
            return false;
        }

        $allDateKeys = [];
        $requiredSlotsPerDate = [];

        $tz = new \DateTimeZone('America/Los_Angeles');

        foreach ($dateRanges as $range) {
            $start = (new \DateTime($range['start']))->setTimezone($tz);
            $end = (new \DateTime($range['end']))->setTimezone($tz);

            $current = clone $start;
            while ($current <= $end) {
                $dateKey = $current->format('Y-m-d');
                $allDateKeys[$dateKey] = true;
                $current->modify('+1 day');
            }

            $rangeSlots = TimeSlotHelper::getRequiredTimeSlots($range['start'], $range['end']);
            foreach ($rangeSlots as $dateKey => $slots) {
                if (! isset($requiredSlotsPerDate[$dateKey])) {
                    $requiredSlotsPerDate[$dateKey] = [];
                }
                $requiredSlotsPerDate[$dateKey] = array_unique(
                    array_merge($requiredSlotsPerDate[$dateKey], $slots)
                );
            }
        }

        if (empty($allDateKeys)) {
            return false;
        }

        $bookingDates = array_keys($allDateKeys);

        $availabilities = $caregiver->availabilities->filter(function ($avail) use ($bookingDates) {
            $availDate = $avail->date instanceof \DateTimeInterface
                ? $avail->date->format('Y-m-d')
                : date('Y-m-d', strtotime($avail->date));

            return in_array($availDate, $bookingDates);
        });

        if ($availabilities->isEmpty()) {
            return false;
        }

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

            $usedSlots = $availability->usedSlots
                ->where('date', $date)
                ->pluck('time_slot')
                ->toArray();

            $freeSlots = array_diff($availableSlots, $usedSlots);
            $coveredSlots = array_intersect($requiredSlots, $freeSlots);

            if (count($coveredSlots) < count($requiredSlots)) {
                return false;
            }
        }

        // Buffer time check — ensure adequate gap between existing bookings
        if ($bufferMinutes > 0 && ! empty($dateRanges)) {
            $caregiverBookings = $existingBookings[$caregiver->id] ?? collect();

            foreach ($dateRanges as $range) {
                $newStart = new \DateTime($range['start']);
                $newEnd = new \DateTime($range['end']);

                foreach ($caregiverBookings as $existing) {
                    $bufferedStart = (clone $existing->start_datetime)->subMinutes($bufferMinutes);
                    $bufferedEnd = (clone $existing->end_datetime)->addMinutes($bufferMinutes);

                    if ($newStart < $bufferedEnd && $newEnd > $bufferedStart) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * Check if caregiver's age-group specialties match the service type or baby_specialist preference.
     */
    protected function matchesServiceType(Caregiver $caregiver, ?string $serviceType, array $sitterPreferences = []): bool
    {
        $specialtyMap = [
            ServiceType::Babysitter->value => ['Babies', 'Toddlers', 'Preschool', 'School Age'],
            ServiceType::GroupChildcareInvoiced->value => ['Babies', 'Toddlers', 'Preschool', 'School Age'],
        ];

        $expectedSpecialties = $specialtyMap[$serviceType] ?? [];

        if (in_array(SitterPreference::BabySpecialist->value, $sitterPreferences)) {
            $expectedSpecialties[] = 'Babies';
        }

        if (! empty($expectedSpecialties)) {
            return $caregiver->specialtyTypes->pluck('name')
                ->intersect(array_unique($expectedSpecialties))
                ->isNotEmpty();
        }

        if ($serviceType === ServiceType::CompanionCare->value) {
            return $caregiver->attributes
                ->firstWhere('slug', 'special_needs')?->pivot->value === 'true';
        }

        return false;
    }

    /**
     * Check if caregiver's EAV attributes match sitter preferences.
     */
    protected function matchesSitterPreferences(Caregiver $caregiver, array $sitterPreferences): bool
    {
        $preferenceAttributeMap = [
            SitterPreference::SpecialNeedsCare->value => 'special_needs',
        ];

        foreach ($sitterPreferences as $preference) {
            $attributeSlug = $preferenceAttributeMap[$preference] ?? null;
            if ($attributeSlug) {
                $attribute = $caregiver->attributes->firstWhere('slug', $attributeSlug);
                if ($attribute && $attribute->pivot->value === 'true') {
                    return true;
                }
            }
        }

        return false;
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
