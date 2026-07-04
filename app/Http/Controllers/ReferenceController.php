<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmitReferenceRequest;
use App\Models\ReferenceRequest;
use App\Models\User;
use App\Notifications\ReferenceCompletedNotification;
use Illuminate\Support\Facades\Notification;

class ReferenceController extends Controller
{
    public function show(string $token)
    {
        $reference = ReferenceRequest::where('token', $token)->firstOrFail();

        if ($reference->submitted_at) {
            return inertia('public/references/submitted', [
                'referenceName' => $reference->reference_name,
                'applicantName' => $reference->caregiver->first_name.' '.$reference->caregiver->last_name,
            ]);
        }

        return inertia('public/references/submit', [
            'token' => $token,
            'referenceName' => $reference->reference_name,
            'applicantName' => $reference->caregiver->first_name.' '.$reference->caregiver->last_name,
            'defaults' => [
                'relationship' => $reference->relationship,
                'years_known' => $reference->years_known,
                'rating_reliability' => $reference->rating_reliability,
                'rating_trustworthiness' => $reference->rating_trustworthiness,
                'rating_maturity' => $reference->rating_maturity,
                'rating_communication' => $reference->rating_communication,
                'rating_warmth' => $reference->rating_warmth,
                'rating_overall_recommendation' => $reference->rating_overall_recommendation,
                'rating_appearance' => $reference->rating_appearance,
                'rating_punctuality' => $reference->rating_punctuality,
                'strengths' => $reference->strengths,
                'concerns' => $reference->concerns,
                'additional_comments' => $reference->additional_comments,
                'background_drug_alcohol' => $reference->background_drug_alcohol,
                'background_tobacco' => $reference->background_tobacco,
                'trust_own_child' => $reference->trust_own_child,
                'reason_not_care' => $reference->reason_not_care,
                'reason_not_care_explanation' => $reference->reason_not_care_explanation,
            ],
        ]);
    }

    public function store(string $token, SubmitReferenceRequest $request)
    {
        $reference = ReferenceRequest::where('token', $token)->firstOrFail();

        if ($reference->submitted_at) {
            return back()->withErrors(['token' => 'This reference has already been submitted.']);
        }

        $reference->update([
            'relationship' => $request->input('relationship'),
            'years_known' => $request->input('years_known'),
            'rating_reliability' => $request->input('rating_reliability'),
            'rating_trustworthiness' => $request->input('rating_trustworthiness'),
            'rating_maturity' => $request->input('rating_maturity'),
            'rating_communication' => $request->input('rating_communication'),
            'rating_warmth' => $request->input('rating_warmth'),
            'rating_overall_recommendation' => $request->input('rating_overall_recommendation'),
            'rating_appearance' => $request->input('rating_appearance'),
            'rating_punctuality' => $request->input('rating_punctuality'),
            'strengths' => $request->input('strengths'),
            'concerns' => $request->input('concerns'),
            'additional_comments' => $request->input('additional_comments'),
            'background_drug_alcohol' => $request->input('background_drug_alcohol'),
            'background_tobacco' => $request->input('background_tobacco'),
            'trust_own_child' => $request->input('trust_own_child'),
            'reason_not_care' => $request->input('reason_not_care'),
            'reason_not_care_explanation' => $request->input('reason_not_care_explanation'),
            'submitted_at' => now(),
        ]);

        // Notify admins
        $admins = User::where('role', 'admin')->get();
        $reference->load('caregiver.application');
        $applicantName = $reference->caregiver->first_name.' '.$reference->caregiver->last_name;
        $reviewUrl = $reference->caregiver->application
            ? route('applications.show', $reference->caregiver->application)
            : null;
        Notification::send($admins, new ReferenceCompletedNotification(
            $reference,
            $applicantName,
            $reviewUrl,
        ));

        return redirect("/references/{$token}")->with('success', 'Your reference has been submitted. Thank you!');
    }
}
