<?php

namespace App\Http\Controllers;

use App\Enums\CaregiverStatus;
use App\Http\Requests\ApplicationActionRequest;
use App\Mail\ApplicantDeclinedMail;
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
        $query = CaregiverApplication::with('caregiver.user', 'caregiver.referenceRequests');

        if ($status = request('status')) {
            $query->whereHas('caregiver', fn ($q) => $q->where('status', $status));
        }

        $applications = $query
            ->orderBy('submitted_at', 'desc')
            ->paginate(20)
            ->through(fn ($app) => [
                'id' => $app->id,
                'caregiver_id' => $app->caregiver_id,
                'applicant_name' => $app->data['personal']['first_name'].' '.$app->data['personal']['last_name'],
                'applicant_email' => $app->caregiver->user->email,
                'status' => $app->caregiver->status->value,
                'status_label' => $app->caregiver->status->label(),
                'submitted_at' => $app->submitted_at?->format('Y-m-d H:i:s'),
                'reference_count' => $app->caregiver->referenceRequests->count(),
                'completed_count' => $app->caregiver->referenceRequests->whereNotNull('submitted_at')->count(),
            ]);

        return Inertia::render('admin/applications/index', [
            'applications' => $applications,
            'filters' => request()->only('status'),
        ]);
    }

    public function show(CaregiverApplication $application)
    {
        $application->load('caregiver.user');

        $caregiver = $application->caregiver;

        $certifications = $caregiver->certifications()
            ->get()
            ->map(fn ($cert) => [
                'id' => $cert->id,
                'name' => $cert->name,
                'expires_required' => $cert->expires_required,
                'expiration_date' => $cert->pivot->expiration_date ? \Carbon\Carbon::parse($cert->pivot->expiration_date)->format('Y-m-d') : null,
                'verified_at' => $cert->pivot->verified_at ? \Carbon\Carbon::parse($cert->pivot->verified_at)->format('Y-m-d') : null,
                'notes' => $cert->pivot->notes,
            ]);

        $references = $caregiver->referenceRequests()
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

        return Inertia::render('admin/applications/show', [
            'application' => [
                'id' => $application->id,
                'submitted_at' => $application->submitted_at?->format('Y-m-d H:i:s'),
                'data' => $application->data,
                'caregiver' => [
                    'id' => $caregiver->id,
                    'first_name' => $caregiver->first_name,
                    'last_name' => $caregiver->last_name,
                    'email' => $caregiver->user->email,
                    'status' => $caregiver->status->value,
                    'status_label' => $caregiver->status->label(),
                ],
            ],
            'references' => $references,
            'certifications' => $certifications,
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

    public function approve(CaregiverApplication $application, ApplicationActionRequest $request)
    {
        $caregiver = $application->caregiver;

        abort_if($caregiver->status !== CaregiverStatus::Applicant, 422, 'Application must be in Applicant status to approve.');

        $caregiver->update(['status' => CaregiverStatus::UnderReview]);

        return back()->with('success', 'Application moved to Under Review.');
    }

    public function scheduleInterview(CaregiverApplication $application, ApplicationActionRequest $request)
    {
        $caregiver = $application->caregiver;

        abort_if($caregiver->status !== CaregiverStatus::UnderReview, 422, 'Application must be Under Review to schedule an interview.');

        $caregiver->update(['status' => CaregiverStatus::InterviewScheduled]);

        return back()->with('success', 'Interview scheduled.');
    }

    public function startBackgroundCheck(CaregiverApplication $application, ApplicationActionRequest $request)
    {
        $caregiver = $application->caregiver;

        abort_if($caregiver->status !== CaregiverStatus::InterviewScheduled, 422, 'Interview must be scheduled before starting a background check.');

        $caregiver->update(['status' => CaregiverStatus::BackgroundCheck]);

        return back()->with('success', 'Background check started.');
    }

    public function hire(CaregiverApplication $application, ApplicationActionRequest $request)
    {
        $caregiver = $application->caregiver;

        abort_if($caregiver->status !== CaregiverStatus::BackgroundCheck, 422, 'Background check must be completed before hiring.');

        $caregiver->update(['status' => CaregiverStatus::Active]);

        return back()->with('success', 'Applicant hired!');
    }

    public function decline(CaregiverApplication $application, ApplicationActionRequest $request)
    {
        $caregiver = $application->caregiver;

        abort_if(in_array($caregiver->status, CaregiverStatus::terminal()), 422, 'Cannot decline a terminal status.');

        $caregiver->update(['status' => CaregiverStatus::Inactive]);

        $applicantName = $caregiver->first_name.' '.$caregiver->last_name;
        $reason = $request->input('note');

        if ($caregiver->user) {
            Mail::to($caregiver->user->email)->queue(
                new ApplicantDeclinedMail($applicantName, $reason),
            );
        }

        return back()->with('success', 'Application declined. Applicant has been notified.');
    }
}
