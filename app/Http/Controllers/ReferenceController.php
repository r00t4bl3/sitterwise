<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmitReferenceRequest;
use App\Mail\ReferenceCompletedMail;
use App\Models\ReferenceRequest;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

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
                'strengths' => $reference->strengths,
                'concerns' => $reference->concerns,
                'additional_comments' => $reference->additional_comments,
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
            'strengths' => $request->input('strengths'),
            'concerns' => $request->input('concerns'),
            'additional_comments' => $request->input('additional_comments'),
            'submitted_at' => now(),
        ]);

        // Notify admins
        $adminEmails = User::whereIn('role', ['admin', 'super_admin'])->pluck('email');
        $applicantName = $reference->caregiver->first_name.' '.$reference->caregiver->last_name;
        foreach ($adminEmails as $adminEmail) {
            Mail::to($adminEmail)->queue(new ReferenceCompletedMail(
                $reference->reference_name,
                $applicantName,
            ));
        }

        return redirect("/references/{$token}")->with('success', 'Your reference has been submitted. Thank you!');
    }
}
