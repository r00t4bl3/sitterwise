<?php

namespace App\Http\Controllers;

use App\Enums\CaregiverStatus;
use App\Http\Requests\StoreInterviewEvaluationRequest;
use App\Models\CaregiverApplication;
use App\Models\CaregiverInterview;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class InterviewController extends Controller
{
    public function create(CaregiverApplication $application)
    {
        $caregiver = $application->caregiver;

        if ($caregiver->status !== CaregiverStatus::InterviewScheduled) {
            abort(422, 'Interview can only be evaluated when status is Interview Scheduled.');
        }

        $existing = CaregiverInterview::where('caregiver_id', $caregiver->id)
            ->where('application_id', $application->id)
            ->latest()
            ->first();

        $sponsor = $caregiver->sponsors()->first();

        return Inertia::render('admin/interviews/evaluate', [
            'application' => [
                'id' => $application->id,
                'submitted_at' => $application->submitted_at,
            ],
            'caregiver' => [
                'id' => $caregiver->id,
                'first_name' => $caregiver->first_name,
                'last_name' => $caregiver->last_name,
                'initials' => strtoupper(substr($caregiver->first_name, 0, 1).substr($caregiver->last_name, 0, 1)),
                'email' => $caregiver->user->email,
            ],
            'sponsor' => $sponsor ? [
                'name' => $sponsor->first_name.' '.$sponsor->last_name,
                'relationship' => $sponsor->relationship,
            ] : null,
            'existing' => $existing ? [
                'scores' => $existing->scores,
                'composite' => $existing->composite,
                'notes' => $existing->notes,
                'status' => $existing->status,
            ] : null,
        ]);
    }

    public function store(StoreInterviewEvaluationRequest $request, CaregiverApplication $application)
    {
        $caregiver = $application->caregiver;

        $scores = $request->input('scores');
        $composite = $this->calculateComposite($scores);
        $status = $request->input('status');

        CaregiverInterview::updateOrCreate(
            [
                'caregiver_id' => $caregiver->id,
                'application_id' => $application->id,
            ],
            [
                'evaluator_id' => Auth::id(),
                'scores' => $scores,
                'composite' => $composite,
                'notes' => $request->input('notes'),
                'status' => $status,
                'evaluated_at' => now(),
            ],
        );

        if ($status === 'completed') {
            $caregiver->update(['status' => CaregiverStatus::BackgroundCheck]);
        } elseif ($status === 'declined') {
            $caregiver->update(['status' => CaregiverStatus::Inactive]);
        }

        return redirect()->route('applications.show', $application->id)
            ->with('success', $status === 'completed'
                ? 'Interview saved and candidate advanced to background check.'
                : ($status === 'declined'
                    ? 'Candidate declined.'
                    : 'Interview draft saved.'));
    }

    private function calculateComposite(array $scores): int
    {
        $all = array_merge(
            array_values($scores['soft_skills'] ?? []),
            array_values($scores['professionalism'] ?? []),
        );

        return array_sum($all);
    }
}
