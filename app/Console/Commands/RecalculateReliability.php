<?php

namespace App\Console\Commands;

use App\Models\Caregiver;
use App\Models\CaregiverInternalRating;
use App\Models\CaregiverInterview;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:recalculate-reliability {--caregiver= : Recalculate for a single caregiver by ID}')]
#[Description('Recalculate reliability and composite scores for caregivers. Excludes cancelled_by_sitterwise from both penalties and completions.')]
class RecalculateReliability extends Command
{
    public function handle()
    {
        if ($caregiverId = $this->option('caregiver')) {
            $caregivers = Caregiver::whereKey($caregiverId)->get();
            if ($caregivers->isEmpty()) {
                $this->error("Caregiver with ID {$caregiverId} not found.");

                return 1;
            }
        } else {
            $caregivers = Caregiver::query()
                ->whereHas('assignments', fn ($q) => $q->whereNotNull('resolution'))
                ->get();
        }

        $count = 0;
        foreach ($caregivers as $caregiver) {
            $this->recalculateForCaregiver($caregiver);
            $count++;
        }

        $this->info("Reliability recalculated for {$count} caregiver(s).");

        return 0;
    }

    private function recalculateForCaregiver(Caregiver $caregiver): void
    {
        $rating = $caregiver->internalRating()->firstOrNew([]);

        $this->recalculateReliabilityScore($caregiver, $rating);
        $rating->composite_score = $this->calculateComposite($caregiver, $rating);

        $rating->save();
    }

    public function handleSingle(Caregiver $caregiver, CaregiverInternalRating $rating): ?float
    {
        $this->recalculateReliabilityScore($caregiver, $rating);
        $composite = $this->calculateComposite($caregiver, $rating);
        $rating->composite_score = $composite;

        return $composite;
    }

    private function recalculateReliabilityScore(Caregiver $caregiver, CaregiverInternalRating $rating): void
    {
        $assignments = $caregiver->assignments()
            ->whereNotNull('resolution')
            ->get();

        $backs = $assignments->whereIn('resolution', ['backed_out', 'no_show'])->count();
        $completed = $assignments->where('resolution', 'completed')->count();
        $totalRelevant = $backs + $completed;

        $reliabilityScore = null;
        if ($totalRelevant > 0) {
            $score = 5.0 - ($backs * 0.5) + (floor($completed / 10) * 0.1);
            $reliabilityScore = round(max(0, min(5.0, $score)), 2);
        }

        $rating->reliability_score = $reliabilityScore;
        $rating->reliability_cached_at = now();
    }

    public function calculateComposite(Caregiver $caregiver, CaregiverInternalRating $rating): ?float
    {
        $interviewComposite = CaregiverInterview::where('caregiver_id', $caregiver->id)
            ->where('status', 'completed')
            ->value('composite');

        $components = [];
        $weights = [];

        if ($interviewComposite !== null) {
            $components[] = ($interviewComposite / 36) * 100;
            $weights[] = 20;
        }

        if ($rating->communication_score !== null) {
            $components[] = ((float) $rating->communication_score / 5) * 100;
            $weights[] = 30;
        }

        $effectiveReliability = $rating->reliability_override ?? $rating->reliability_score;
        if ($effectiveReliability !== null) {
            $components[] = ((float) $effectiveReliability / 5) * 100;
            $weights[] = 50;
        }

        if (empty($components)) {
            return null;
        }

        $totalWeight = array_sum($weights);
        $weightedSum = 0;
        foreach ($components as $i => $component) {
            $weightedSum += $component * ($weights[$i] / $totalWeight);
        }

        return round($weightedSum, 2);
    }
}
