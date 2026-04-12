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

        // Get all active caregivers
        $caregivers = Caregiver::with([
            'status',
            'certifications',
            'specialtyTypes',
            'availabilities',
        ])
            ->whereHas('status', fn ($q) => $q->where('is_active', true))
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

        // 6. Availability for booking dates (+30)
        if ($startDate && $endDate && $this->isAvailable($caregiver, $startDate, $endDate)) {
            $score += 30;
        }

        return $score;
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
     */
    protected function matchesServiceType(
        Caregiver $caregiver,
        string $serviceType
    ): bool {
        // Map service type to specialty type slug/name
        $specialtyMap = [
            ServiceType::Babysitter->value => ['babysitter', 'childcare'],
            ServiceType::Petsitter->value => ['petsitter', 'pet_care'],
            ServiceType::CompanionCare->value => ['companion', 'elderly_care'],
        ];

        $expectedSpecialties = $specialtyMap[$serviceType] ?? [];

        if (empty($expectedSpecialties)) {
            return true; // No specific match needed
        }

        return $caregiver->specialtyTypes->pluck('name')->intersect($expectedSpecialties)->isNotEmpty();
    }

    /**
     * Check if caregiver is available during the date range.
     */
    protected function isAvailable(
        Caregiver $caregiver,
        string $startDate,
        string $endDate
    ): bool {
        // Check if caregiver has availability records for the dates
        $availableDates = $caregiver->availabilities
            ->pluck('date')
            ->map(fn ($d) => $d->toDateString());

        $bookingDates = collect();
        $current = new \DateTime($startDate);
        $end = new \DateTime($endDate);

        while ($current <= $end) {
            $bookingDates->add($current->format('Y-m-d'));
            $current->modify('+1 day');
        }

        // Caregiver must have availability for all booking dates
        return $bookingDates->diff($availableDates)->isEmpty();
    }
}
