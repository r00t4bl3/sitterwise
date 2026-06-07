<?php

namespace App\Http\Controllers;

use App\Models\CaregiverApplication;
use App\Models\CaregiverInterview;
use App\Models\CaregiverInterviewTalkingPoint;
use App\Models\InterviewTalkingPoint;
use Illuminate\Http\Request;

class CaregiverInterviewTalkingPointController extends Controller
{
    public function index(CaregiverApplication $application)
    {
        $caregiver = $application->caregiver;
        $interview = CaregiverInterview::where('caregiver_id', $caregiver->id)
            ->where('application_id', $application->id)
            ->latest()
            ->firstOrFail();

        if ($interview->talkingPoints()->count() === 0) {
            $this->seedTalkingPoints($interview);
        }

        return $interview->talkingPoints()->get();
    }

    public function store(Request $request, CaregiverApplication $application)
    {
        $validated = $request->validate([
            'label' => 'required|string|max:255',
        ]);

        $caregiver = $application->caregiver;
        $interview = CaregiverInterview::where('caregiver_id', $caregiver->id)
            ->where('application_id', $application->id)
            ->latest()
            ->firstOrFail();

        $maxSort = $interview->talkingPoints()->max('sort_order') ?? 0;

        $point = $interview->talkingPoints()->create([
            'talking_point_id' => null,
            'label' => $validated['label'],
            'sort_order' => $maxSort + 1,
        ]);

        return response()->json($point);
    }

    public function toggle(CaregiverApplication $application, CaregiverInterviewTalkingPoint $point)
    {
        $caregiver = $application->caregiver;
        $interview = CaregiverInterview::where('caregiver_id', $caregiver->id)
            ->where('application_id', $application->id)
            ->latest()
            ->firstOrFail();

        abort_if($point->caregiver_interview_id !== $interview->id, 404);

        $point->update(['is_checked' => ! $point->is_checked]);

        return response()->json($point);
    }

    public function update(Request $request, CaregiverApplication $application, CaregiverInterviewTalkingPoint $point)
    {
        $validated = $request->validate([
            'label' => 'sometimes|string|max:255',
            'notes' => 'nullable|string|max:5000',
        ]);

        $caregiver = $application->caregiver;
        $interview = CaregiverInterview::where('caregiver_id', $caregiver->id)
            ->where('application_id', $application->id)
            ->latest()
            ->firstOrFail();

        abort_if($point->caregiver_interview_id !== $interview->id, 404);

        $point->update(array_filter($validated, fn ($v) => $v !== null));

        return response()->json($point);
    }

    public function destroy(CaregiverApplication $application, CaregiverInterviewTalkingPoint $point)
    {
        $caregiver = $application->caregiver;
        $interview = CaregiverInterview::where('caregiver_id', $caregiver->id)
            ->where('application_id', $application->id)
            ->latest()
            ->firstOrFail();

        abort_if($point->caregiver_interview_id !== $interview->id, 404);

        $point->delete();

        return response()->json(['deleted' => true]);
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
