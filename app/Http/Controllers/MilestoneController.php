<?php

namespace App\Http\Controllers;

use App\Models\CaregiverInternalRating;
use App\Support\Settings;
use Inertia\Inertia;

class MilestoneController extends Controller
{
    public function index()
    {
        $caregiver = auth()->user()->caregiver;

        $completedJobCount = $caregiver->bookings()
            ->whereIn('status', ['completed', 'paid'])
            ->count();

        $rating = $caregiver->rating !== null
            ? round($caregiver->rating * 2) / 2
            : null;

        $effectiveReliability = $caregiver->internalRating?->effectiveReliability();
        $reliabilityPercent = $effectiveReliability !== null ? round($effectiveReliability * 20) : null;

        $assignments = $caregiver->assignments()
            ->whereNotNull('resolution')
            ->orderBy('assigned_at', 'desc')
            ->get();

        $streak = 0;
        foreach ($assignments as $assignment) {
            if ($assignment->resolution === 'completed') {
                $streak++;
            } elseif ($assignment->resolution !== 'reassigned') {
                break;
            }
        }

        $trustlineCert = $caregiver->certifications()
            ->where('certification_type_id', 3)
            ->wherePivot('verified_at', '!=', null)
            ->first();

        $teamAvgReliability = CaregiverInternalRating::whereHas('caregiver', function ($q) {
            $q->where('status', 'active');
        })->get()->map(function ($r) {
            return $r->effectiveReliability();
        })->filter()->average();

        $teamAvgPercent = $teamAvgReliability !== null ? round($teamAvgReliability * 20) : null;

        $notificationsQuery = $caregiver->bookingNotifications();
        $totalOffered = (clone $notificationsQuery)->count();
        $totalAccepted = (clone $notificationsQuery)->where('claimed', true)->count();
        $acceptanceRate = $totalOffered > 0 ? round($totalAccepted / $totalOffered * 100) : 0;

        $avgResponseTime = (clone $notificationsQuery)
            ->where('claimed', true)
            ->whereNotNull('responded_at')
            ->get()
            ->avg(fn ($n) => $n->notified_at->diffInHours($n->responded_at));

        $resolutions = $caregiver->assignments()
            ->whereNotNull('resolution')
            ->get();

        $backs = $resolutions->whereIn('resolution', ['backed_out', 'no_show'])->count();
        $completedAssigned = $resolutions->where('resolution', 'completed')->count();
        $totalRelevant = $backs + $completedAssigned;
        $backOutRate = $totalRelevant > 0 ? round($backs / $totalRelevant * 100) : 0;

        $jobsThisMonth = $caregiver->bookings()
            ->whereIn('status', ['completed', 'paid'])
            ->where('end_datetime', '>=', now()->startOfMonth())
            ->count();

        $jobsThisQuarter = $caregiver->bookings()
            ->whereIn('status', ['completed', 'paid'])
            ->where('end_datetime', '>=', now()->startOfQuarter())
            ->count();

        $lastJob = $caregiver->bookings()
            ->whereIn('status', ['completed', 'paid'])
            ->orderBy('end_datetime', 'desc')
            ->first();

        $memberSince = $caregiver->created_at;

        $declined = $totalOffered - $totalAccepted;

        return Inertia::render('caregiver/milestones', [
            'milestones' => [
                'completedJobs' => $completedJobCount,
                'sinceJoined' => $memberSince->format('F Y'),
                'rating' => $rating,
                'ratingCount' => $caregiver->ratings()->count(),
                'reliabilityPercent' => $reliabilityPercent,
                'teamAvgPercent' => $teamAvgPercent,
                'jobStreak' => $streak,
                'trustlineCertified' => $trustlineCert !== null,
                'trustlineProgress' => $completedJobCount,
                'trustlineThreshold' => Settings::get('trustline.jobs_threshold', config('trustline.jobs_threshold', 10)),
                'trustlineReward' => Settings::get('trustline.reward_amount', config('trustline.reward_amount', 140)),
            ],
            'engagement' => [
                'jobsOffered' => $totalOffered,
                'jobsAccepted' => $totalAccepted,
                'acceptanceRate' => $acceptanceRate,
                'avgResponseTimeHours' => $avgResponseTime !== null ? round($avgResponseTime, 1) : null,
                'declined' => $declined,
                'declinedPercent' => $totalOffered > 0 ? round($declined / $totalOffered * 100) : 0,
                'backOutRate' => $backOutRate,
                'jobsThisMonth' => $jobsThisMonth,
                'jobsThisQuarter' => $jobsThisQuarter,
                'lastJobDate' => $lastJob?->end_datetime?->format('M j, Y'),
                'memberSince' => $memberSince->format('M j, Y'),
            ],
        ]);
    }
}
