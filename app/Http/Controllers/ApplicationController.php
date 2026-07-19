<?php

namespace App\Http\Controllers;

use App\Enums\CaregiverStatus;
use App\Http\Requests\ApplicationActionRequest;
use App\Http\Requests\UpdateReferenceRequest;
use App\Mail\ApplicantDeclinedMail;
use App\Mail\ApplicantHiredMail;
use App\Mail\ReferenceRequestMail;
use App\Models\CaregiverApplication;
use App\Models\CertificationType;
use App\Models\OnboardingChecklistItem;
use App\Models\ReferenceRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ApplicationController extends Controller
{
    public function index()
    {
        $query = CaregiverApplication::with('caregiver.user', 'caregiver.referenceRequests');

        if ($search = request('search')) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('caregiver', function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                })->orWhereHas('caregiver.user', function ($q) use ($search) {
                    $q->where('email', 'like', "%{$search}%");
                });
            });
        }

        if ($status = request('status')) {
            $query->whereHas('caregiver', fn ($q) => $q->where('status', $status));
        }

        $applications = $query
            ->orderBy('submitted_at', 'desc')
            ->paginate(10)
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
            'filters' => request()->only(['status', 'search']),
        ]);
    }

    public function show(CaregiverApplication $application)
    {
        $application->load('caregiver.user', 'interview.evaluator');

        $caregiver = $application->caregiver;

        $certifications = $caregiver->certifications()
            ->get()
            ->map(fn ($cert) => [
                'id' => $cert->id,
                'name' => $cert->name,
                'expires_required' => $cert->expires_required,
                'expiration_date' => $cert->pivot->expiration_date ? Carbon::parse($cert->pivot->expiration_date)->format('Y-m-d') : null,
                'verified_at' => $cert->pivot->verified_at ? Carbon::parse($cert->pivot->verified_at)->format('Y-m-d') : null,
                'file_path' => $cert->pivot->file_path,
                'file_url' => $cert->pivot->file_path
                    ? route('caregivers.certifications.document', [$caregiver, $cert->id])
                    : null,
                'notes' => $cert->pivot->notes,
            ]);

        $checklistItems = $caregiver->onboardingChecklistItems()
            ->orderBy('id')
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'item_key' => $item->item_key,
                'label' => $item->label,
                'description' => $item->description,
                'completed_at' => $item->completed_at?->format('Y-m-d H:i:s'),
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
                'rating_appearance' => $ref->rating_appearance,
                'rating_punctuality' => $ref->rating_punctuality,
                'strengths' => $ref->strengths,
                'concerns' => $ref->concerns,
                'additional_comments' => $ref->additional_comments,
                'background_drug_alcohol' => $ref->background_drug_alcohol,
                'background_tobacco' => $ref->background_tobacco,
                'trust_own_child' => $ref->trust_own_child,
                'reason_not_care' => $ref->reason_not_care,
                'reason_not_care_explanation' => $ref->reason_not_care_explanation,
                'submitted_at' => $ref->submitted_at?->format('Y-m-d H:i:s'),
                'created_at' => $ref->created_at?->format('Y-m-d H:i:s'),
            ]);

        $interview = $application->interview;

        return Inertia::render('admin/applications/show', [
            'application' => [
                'id' => $application->id,
                'submitted_at' => $application->submitted_at?->format('Y-m-d H:i:s'),
                'data' => $application->data,
                'photo_url' => ($application->data['personal']['photo'] ?? null)
                    ? Storage::url($application->data['personal']['photo'])
                    : null,
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
            'checklistItems' => $checklistItems,
            'interview' => $interview ? [
                'id' => $interview->id,
                'status' => $interview->status,
                'composite' => $interview->composite,
                'evaluated_at' => $interview->evaluated_at?->format('Y-m-d H:i:s'),
                'evaluator_name' => $interview->evaluator?->name,
            ] : null,
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

    public function updateReference(CaregiverApplication $application, ReferenceRequest $referenceRequest, UpdateReferenceRequest $request)
    {
        if ($referenceRequest->caregiver_id !== $application->caregiver_id) {
            abort(404);
        }

        $validated = $request->validated();

        $ratingFields = [
            'rating_reliability',
            'rating_trustworthiness',
            'rating_maturity',
            'rating_communication',
            'rating_warmth',
            'rating_overall_recommendation',
            'rating_appearance',
            'rating_punctuality',
            'strengths',
            'concerns',
            'additional_comments',
            'background_drug_alcohol',
            'background_tobacco',
            'trust_own_child',
            'reason_not_care',
            'reason_not_care_explanation',
        ];

        $hasResponseData = collect($ratingFields)->contains(fn ($field) => ! empty($validated[$field]));

        $referenceRequest->update(array_merge(
            $validated,
            $hasResponseData && ! $referenceRequest->submitted_at ? ['submitted_at' => now()] : [],
        ));

        if (! empty($validated['is_sponsor'])) {
            $application->caregiver->referenceRequests()
                ->where('id', '!=', $referenceRequest->id)
                ->where('is_sponsor', true)
                ->update(['is_sponsor' => false]);
        }

        return back()->with('success', 'Reference updated successfully.');
    }

    public function destroyReference(CaregiverApplication $application, ReferenceRequest $referenceRequest)
    {
        if ($referenceRequest->caregiver_id !== $application->caregiver_id) {
            abort(404);
        }

        $referenceRequest->delete();

        return back()->with('success', 'Reference removed.');
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

        $caregiver->update(['status' => CaregiverStatus::HiredOnboarding]);

        OnboardingChecklistItem::seedForCaregiver($caregiver);

        $applicantName = $caregiver->first_name.' '.$caregiver->last_name;
        $statusUrl = url('/caregiver/apply/status/'.$caregiver->status_token);

        if ($caregiver->user) {
            Mail::to($caregiver->user->email)->queue(
                new ApplicantHiredMail($applicantName, $statusUrl),
            );
        }

        return back()->with('success', 'Applicant hired! Onboarding checklist created.');
    }

    public function toggleChecklistItem(CaregiverApplication $application, OnboardingChecklistItem $checklistItem, ApplicationActionRequest $request)
    {
        abort_if($checklistItem->caregiver_id !== $application->caregiver_id, 404);

        $isBeingChecked = $checklistItem->completed_at === null;

        if ($isBeingChecked) {
            $checklistItem->update(['completed_at' => now()]);
        } else {
            $checklistItem->update(['completed_at' => null]);
        }

        $caregiver = $application->caregiver;

        $certificationMap = [
            'cpr_uploaded' => 'CPR & First Aid',
            'trustline_submitted' => 'Trustline',
        ];

        if (isset($certificationMap[$checklistItem->item_key])) {
            $certType = CertificationType::where('name', $certificationMap[$checklistItem->item_key])->first();
            if ($certType) {
                $caregiver->certifications()->updateExistingPivot($certType->id, [
                    'verified_at' => $isBeingChecked ? now() : null,
                ]);
            }
        }

        return back();
    }

    public function toggleCertificationVerification(CaregiverApplication $application, CertificationType $certType, ApplicationActionRequest $request)
    {
        $caregiver = $application->caregiver;

        $pivot = $caregiver->certifications()->where('certification_type_id', $certType->id)->first();

        abort_unless($pivot, 422, 'The caregiver does not have this certification type.');

        $wasVerified = $pivot->pivot->verified_at !== null;
        $newValue = $wasVerified ? null : now();

        $caregiver->certifications()->updateExistingPivot($certType->id, [
            'verified_at' => $newValue,
        ]);

        $certificationMap = [
            'CPR & First Aid' => 'cpr_uploaded',
            'Trustline' => 'trustline_submitted',
        ];

        if (isset($certificationMap[$certType->name])) {
            $itemKey = $certificationMap[$certType->name];
            $item = $caregiver->onboardingChecklistItems()
                ->where('item_key', $itemKey)
                ->first();

            if ($item) {
                $item->update(['completed_at' => $newValue]);
            }
        }

        return back();
    }

    public function completeOnboarding(CaregiverApplication $application, ApplicationActionRequest $request)
    {
        $caregiver = $application->caregiver;

        abort_if($caregiver->status !== CaregiverStatus::HiredOnboarding, 422, 'Onboarding must be in progress before completing.');

        $incomplete = $caregiver->onboardingChecklistItems()
            ->whereNull('completed_at')
            ->count();

        if ($incomplete > 0) {
            return back()->with('error', "Cannot complete onboarding — {$incomplete} checklist item(s) still pending.");
        }

        $caregiver->update(['status' => CaregiverStatus::Active]);

        return back()->with('success', 'Onboarding complete! Caregiver is now Active.');
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
