<?php

namespace App\Services\CaregiverRecommendation;

use App\Enums\ServiceType;
use App\Models\Booking;
use App\Models\Caregiver;
use App\Models\Client;
use Illuminate\Support\Collection;

class CaregiverRecommendationService
{
    /**
     * Get recommended caregivers for a client/booking.
     *
     * Returns caregivers with their match scores and badges.
     */
    public function getRecommendedCaregivers(
        Client $client,
        ?Booking $booking = null,
        int $limit = 20
    ): Collection {
        $serviceType = $booking?->service_type;
        $startDate = $booking?->start_datetime;
        $endDate = $booking?->end_datetime;

        // Get all active caregivers, excluding blocked ones
        $caregivers = Caregiver::with([
            'status',
            'certifications',
            'specialtyTypes',
            'availabilities',
        ])
            ->whereHas('status', fn ($q) => $q->where('is_active', true))
            ->whereDoesntHave('blockedClients', fn ($q) => $q->where('client_id', $client->id))
            ->get();

        // Score and rank each caregiver
        $scored = $caregivers->map(function (Caregiver $caregiver) use (
            $client,
            $booking,
            $serviceType,
            $startDate,
            $endDate
        ) {
            $score = $this->calculateScore(
                $caregiver,
                $client,
                $booking,
                $serviceType,
                $startDate,
                $endDate
            );

            return [
                'caregiver' => $caregiver,
                'score' => $score,
                'matchBadge' => $this->getMatchBadge($score),
                'hasBeenNotified' => $this->hasBeenNotified($caregiver, $booking),
            ];
        });

        // Sort by score descending and take top N
        return $scored
            ->sortByDesc('score')
            ->take($limit)
            ->values();
    }

    /**
     * Get match badge label based on score.
     *
     * Badge thresholds:
     * - Excellent Match: score >= 100
     * - Good Match: score 50-99
     * - Fair Match: score 20-49
     * - Available: score < 20
     */
    public function getMatchBadge(float $score): array
    {
        if ($score >= 100) {
            return [
                'label' => 'Excellent Match',
                'color' => 'green',
                'icon' => '🟢',
            ];
        }

        if ($score >= 50) {
            return [
                'label' => 'Good Match',
                'color' => 'yellow',
                'icon' => '🟡',
            ];
        }

        if ($score >= 20) {
            return [
                'label' => 'Fair Match',
                'color' => 'orange',
                'icon' => '🟠',
            ];
        }

        return [
            'label' => 'Available',
            'color' => 'blue',
            'icon' => '🔵',
        ];
    }

    /**
     * Calculate recommendation score for a caregiver.
     *
     * Scoring breakdown:
     * - Previous work with client: +10 points per completed booking
     * - Client's favorite: +25 points
     * - Caregiver rating: +20 points × rating (5-star scale, e.g., 4.5 = 90 pts)
     * - Relevant certifications: +5 points per cert
     * - Service type match: +15 points
     * - Available for booking dates: +30 points
     *
     * Example scores:
     * - Favorite caregiver (4.8★, 5 bookings, 3 certs, available, matches): 226
     * - New caregiver (4.5★, 2 certs, available, matches): 125
     * - Available but no history (3.5★, 1 cert): 85
     * - Barely matches (no rating, no certs): 0
     */
    protected function calculateScore(
        Caregiver $caregiver,
        Client $client,
        ?Booking $booking = null,
        ?string $serviceType = null,
        ?string $startDate = null,
        ?string $endDate = null
    ): float {
        $score = 0.0;

        // 1. Previous work with client (+10 per booking)
        $previousBookings = $this->getPreviousWorkHistory($caregiver, $client);
        $score += $previousBookings * 10;

        // 2. Client's favorite (+25)
        if ($this->isClientFavorite($caregiver, $client)) {
            $score += 25;
        }

        // 3. Caregiver rating (+20 × rating on 5-star scale)
        if ($caregiver->rating) {
            $score += (float) $caregiver->rating * 20;
        }

        // 4. Certifications (+5 per cert)
        $certCount = $caregiver->certifications->count();
        $score += $certCount * 5;

        // 5. Service type match (+15)
        if ($serviceType && $this->matchesServiceType($caregiver, $serviceType)) {
            $score += 15;
        }

        // 6. Availability for booking dates (0, 15, or 30 points)
        if ($startDate && $endDate) {
            $score += $this->getAvailabilityScore($caregiver, $startDate, $endDate);
        }

        return $score;
    }

    /**
     * Check if a caregiver has been notified about a booking.
     */
    protected function hasBeenNotified(Caregiver $caregiver, ?Booking $booking): bool
    {
        if (!$booking || !$booking->exists) {
            return false;
        }

        return $caregiver->bookingNotifications()
            ->where('booking_id', $booking->id)
            ->exists();
    }

    /**
     * Count how many bookings this caregiver has with this client.
     */
    protected function getPreviousWorkHistory(
        Caregiver $caregiver,
        Client $client
    ): int {
        return Booking::where('caregiver_id', $caregiver->id)
            ->where('client_id', $client->id)
            ->whereIn('status', ['completed', 'confirmed'])
            ->count();
    }

    /**
     * Check if caregiver is in client's favorites.
     */
    protected function isClientFavorite(
        Caregiver $caregiver,
        Client $client
    ): bool {
        return $client->favoriteCaregivers()
            ->where('caregiver_id', $caregiver->id)
            ->exists();
    }

    /**
     * Check if caregiver's specialties match the service type.
     *
     * Maps service types to relevant age/care specialties:
     * - Babysitter: Babies, Toddlers, Preschool, School Age
     * - Companion Care: Special Needs
     * - Group Childcare: Babies, Toddlers, Preschool, School Age
     * - Petsitter, Corporate, Comped: No specialty match needed
     */
    protected function matchesServiceType(
        Caregiver $caregiver,
        string $serviceType
    ): bool {
        // Map service type to relevant specialty names
        $specialtyMap = [
            ServiceType::Babysitter->value => ['Babies', 'Toddlers', 'Preschool', 'School Age'],
            ServiceType::CompanionCare->value => ['Special Needs'],
            ServiceType::GroupChildcareInvoiced->value => ['Babies', 'Toddlers', 'Preschool', 'School Age'],
        ];

        $expectedSpecialties = $specialtyMap[$serviceType] ?? [];

        if (empty($expectedSpecialties)) {
            return true; // No specific match needed for this service type
        }

        return $caregiver->specialtyTypes->pluck('name')->intersect($expectedSpecialties)->isNotEmpty();
    }

    /**
     * Calculate availability score for a caregiver during the booking period.
     *
     * Returns:
     * - 30 points: Full coverage of all dates and time slots
     * - 15 points: Partial coverage (at least one time slot on each date)
     * - 0 points: No availability or missing dates
     */
    protected function getAvailabilityScore(
        Caregiver $caregiver,
        string $startDate,
        string $endDate
    ): int {
        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);

        // Generate all dates in the booking range
        $bookingDates = [];
        $current = clone $start;
        while ($current <= $end) {
            $bookingDates[] = $current->format('Y-m-d');
            $current->modify('+1 day');
        }

        // Get caregiver's availabilities for these dates
        // Use whereDate to handle SQLite datetime comparison properly
        $availabilities = $caregiver->availabilities()
            ->where(function ($query) use ($bookingDates) {
                foreach ($bookingDates as $date) {
                    $query->orWhereDate('date', $date);
                }
            })
            ->get();

        if ($availabilities->isEmpty()) {
            return 0;
        }

        // Calculate required time slots for each date
        $requiredSlotsPerDate = $this->getRequiredTimeSlots($startDate, $endDate);

        // Check coverage for each date
        $totalDates = count($bookingDates);
        $fullyCoveredDates = 0;
        $partiallyCoveredDates = 0;

        foreach ($bookingDates as $date) {
            // Find availability for this date (use whereDate for proper comparison)
            $availability = $availabilities->first(function ($avail) use ($date) {
                // Handle Carbon, DateTime, or string dates
                if ($avail->date instanceof \DateTimeInterface) {
                    $availDate = $avail->date->format('Y-m-d');
                } else {
                    // Parse string dates (e.g., "2026-04-13 00:00:00")
                    $availDate = date('Y-m-d', strtotime($avail->date));
                }

                return $availDate === $date;
            });

            if (! $availability || empty($availability->time_slots)) {
                continue; // No availability for this date
            }

            $availableSlots = is_array($availability->time_slots)
                ? $availability->time_slots
                : json_decode($availability->time_slots, true);

            $requiredSlots = $requiredSlotsPerDate[$date] ?? [];

            if (empty($requiredSlots)) {
                $fullyCoveredDates++;

                continue;
            }

            // Check if all required slots are covered
            $coveredSlots = array_intersect($requiredSlots, $availableSlots);
            $coverageRatio = count($coveredSlots) / count($requiredSlots);

            if ($coverageRatio >= 1.0) {
                $fullyCoveredDates++;
            } elseif ($coverageRatio > 0) {
                $partiallyCoveredDates++;
            }
        }

        // Scoring logic
        if ($fullyCoveredDates === $totalDates) {
            return 30; // Full coverage on all dates
        }

        if ($fullyCoveredDates + $partiallyCoveredDates >= $totalDates * 0.5) {
            return 15; // At least 50% of dates have some coverage
        }

        return 0;
    }

    /**
     * Get required time slots based on booking start/end times.
     *
     * Time slot definitions:
     * - Morning: 6:00 AM - 12:00 PM
     * - Afternoon: 12:00 PM - 6:00 PM
     * - Evening: 6:00 PM - 11:00 PM
     */
    protected function getRequiredTimeSlots(
        string $startDate,
        string $endDate
    ): array {
        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);

        $requiredSlots = [];

        // Generate dates and determine required time slots
        $current = clone $start;
        while ($current <= $end) {
            $dateKey = $current->format('Y-m-d');
            $slots = [];

            // Determine time slots needed based on booking times
            $dayStart = ($current->format('Y-m-d') === $start->format('Y-m-d')) ? $start : new \DateTime($dateKey.' 00:00:00');
            $dayEnd = ($current->format('Y-m-d') === $end->format('Y-m-d')) ? $end : new \DateTime($dateKey.' 23:59:59');

            // Check if any part of the booking falls in each time slot
            $morningStart = new \DateTime($dateKey.' 06:00:00');
            $morningEnd = new \DateTime($dateKey.' 12:00:00');
            $afternoonStart = new \DateTime($dateKey.' 12:00:00');
            $afternoonEnd = new \DateTime($dateKey.' 18:00:00');
            $eveningStart = new \DateTime($dateKey.' 18:00:00');
            $eveningEnd = new \DateTime($dateKey.' 23:00:00');

            // Check overlap with each slot
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
}
