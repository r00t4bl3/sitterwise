<?php

namespace App\Http\Controllers;

use App\Enums\CaregiverStatus;
use App\Http\Requests\StoreInterviewEvaluationRequest;
use App\Models\CaregiverApplication;
use App\Models\CaregiverInterview;
use App\Models\InterviewTalkingPoint;
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

        $interview = CaregiverInterview::firstOrCreate(
            [
                'caregiver_id' => $caregiver->id,
                'application_id' => $application->id,
            ],
            [
                'evaluator_id' => Auth::id(),
                'scores' => ['soft_skills' => [], 'professionalism' => []],
                'composite' => 0,
                'status' => 'draft',
            ],
        );

        $sponsor = $caregiver->sponsors()->first();

        // Seed talking points from master template if first load
        if ($interview->talkingPoints()->count() === 0) {
            $this->seedTalkingPoints($interview);
        }

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
            'existing' => [
                'scores' => $interview->scores,
                'composite' => $interview->composite,
                'notes' => $interview->notes,
                'status' => $interview->status,
            ],
            'talkingPoints' => $interview->talkingPoints,
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

    private function seedTalkingPoints(CaregiverInterview $interview): void
    {
        $templatePoints = InterviewTalkingPoint::active()->ordered()->get();

        foreach ($templatePoints as $template) {
            $interview->talkingPoints()->create([
                'talking_point_id' => $template->id,
                'label' => $template->label,
                'sort_order' => $template->sort_order,
            ]);
        }
    }
}
