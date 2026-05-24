<?php

namespace App\Http\Controllers;

use App\Mail\ReferenceRequestMail;
use App\Models\CaregiverApplication;
use App\Models\ReferenceRequest;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ApplicationController extends Controller
{
    public function index()
    {
        $applications = CaregiverApplication::with('caregiver.user', 'caregiver.referenceRequests')
            ->orderBy('submitted_at', 'desc')
            ->paginate(20)
            ->through(fn ($app) => [
                'id' => $app->id,
                'caregiver_id' => $app->caregiver_id,
                'applicant_name' => $app->data['personal']['first_name'].' '.$app->data['personal']['last_name'],
                'applicant_email' => $app->caregiver->user->email,
                'submitted_at' => $app->submitted_at?->format('Y-m-d H:i:s'),
                'reference_count' => $app->caregiver->referenceRequests->count(),
                'completed_count' => $app->caregiver->referenceRequests->whereNotNull('submitted_at')->count(),
            ]);

        return Inertia::render('applications/index', [
            'applications' => $applications,
        ]);
    }

    public function show(CaregiverApplication $application)
    {
        $application->load('caregiver.user');

        $references = $application->caregiver->referenceRequests()
            ->orderBy('is_sponsor', 'desc')
            ->orderBy('created_at')
            ->get()
            ->map(fn ($ref) => [
                'id' => $ref->id,
                'token' => $ref->token,
                'reference_name' => $ref->reference_name,
                'reference_email' => $ref->reference_email,
                'relationship' => $ref->relationship,
                'years_known' => $ref->years_known,
                'is_sponsor' => $ref->is_sponsor,
                'rating_reliability' => $ref->rating_reliability,
                'rating_trustworthiness' => $ref->rating_trustworthiness,
                'rating_maturity' => $ref->rating_maturity,
                'rating_communication' => $ref->rating_communication,
                'rating_warmth' => $ref->rating_warmth,
                'rating_overall_recommendation' => $ref->rating_overall_recommendation,
                'strengths' => $ref->strengths,
                'concerns' => $ref->concerns,
                'additional_comments' => $ref->additional_comments,
                'submitted_at' => $ref->submitted_at?->format('Y-m-d H:i:s'),
                'created_at' => $ref->created_at?->format('Y-m-d H:i:s'),
            ]);

        return Inertia::render('applications/show', [
            'application' => [
                'id' => $application->id,
                'submitted_at' => $application->submitted_at?->format('Y-m-d H:i:s'),
                'data' => $application->data,
                'caregiver' => [
                    'id' => $application->caregiver->id,
                    'first_name' => $application->caregiver->first_name,
                    'last_name' => $application->caregiver->last_name,
                    'email' => $application->caregiver->user->email,
                ],
            ],
            'references' => $references,
        ]);
    }

    public function resendReference(CaregiverApplication $application, ReferenceRequest $referenceRequest)
    {
        if ($referenceRequest->caregiver_id !== $application->caregiver_id) {
            abort(404);
        }

        if ($referenceRequest->submitted_at) {
            return back()->with('error', 'Cannot resend — this reference has already been completed.');
        }

        $newToken = Str::random(32);
        $referenceRequest->update(['token' => $newToken]);

        $applicantName = $application->data['personal']['first_name'].' '.$application->data['personal']['last_name'];

        Mail::to($referenceRequest->reference_email)->queue(new ReferenceRequestMail(
            $referenceRequest->reference_name,
            $applicantName,
            $newToken,
        ));

        return back()->with('success', 'Reference request resent to '.$referenceRequest->reference_email);
    }
}
