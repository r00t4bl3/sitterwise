<?php

namespace App\Http\Controllers;

use App\Enums\CaregiverStatus;
use App\Enums\ForeignLanguage;
use App\Http\Requests\StoreCaregiverApplicationRequest;
use App\Mail\ApplicantConfirmationMail;
use App\Mail\ReferenceRequestMail;
use App\Models\AttributeDefinition;
use App\Models\Caregiver;
use App\Models\CaregiverAgreement;
use App\Models\CaregiverApplication;
use App\Models\CertificationType;
use App\Models\IncompleteApplication;
use App\Models\Location;
use App\Models\ReferenceRequest;
use App\Models\SpecialtyType;
use App\Models\User;
use App\Notifications\AdminNewApplicationNotification;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class CaregiverApplicationController extends Controller
{
    public function showVerifyEmail()
    {
        return inertia('public/caregiver-apply/verify-email');
    }

    public function sendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $email = $request->input('email');

        // Check if email already exists
        if (User::where('email', $email)->exists()) {
            Log::channel('submission')->warning('Email verification: already registered', ['email' => $email]);

            return back()->withErrors(['email' => 'This email is already registered. Please log in or use a different email.']);
        }

        // Generate 6-digit OTP
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store OTP in cache (10 minutes)
        Cache::put("otp_{$email}", $otp, 600);

        // Send OTP via email
        Mail::raw("Your Sitterwise verification code is: {$otp}", function ($message) use ($email) {
            $message->to($email)
                ->subject('Your Sitterwise Verification Code');
        });

        Log::channel('submission')->info('Email verification: OTP sent', ['email' => $email]);

        return back()->with('success', 'Verification code sent to your email.');
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string|size:6',
        ]);

        $email = $request->input('email');
        $otp = $request->input('otp');

        // Allow testing bypass in non-production environments
        if (app()->environment() !== 'production' && $otp === '000000') {
            Log::channel('submission')->info('Email verification: bypass OTP used', ['email' => $email]);

            Session::put('verified_email', $email);
            Session::put('verified_at', now());

            return redirect()->route('caregiver.apply');
        }

        $storedOtp = Cache::get("otp_{$email}");

        if (! $storedOtp || $storedOtp !== $otp) {
            Log::channel('submission')->warning('Email verification: invalid OTP attempt', ['email' => $email]);

            return back()->withErrors(['otp' => 'Invalid verification code. Please try again.']);
        }

        // Store verified email in session
        Log::channel('submission')->info('Email verification: verified', ['email' => $email]);

        Session::put('verified_email', $email);
        Session::put('verified_at', now());

        // Clear OTP from cache
        Cache::forget("otp_{$email}");

        return redirect()->route('caregiver.apply');
    }

    public function showWizard()
    {
        return inertia('public/caregiver-apply/wizard', [
            'verifiedEmail' => Session::get('verified_email'),
            'foreignLanguages' => collect(ForeignLanguage::cases())
                ->mapWithKeys(fn ($lang) => [$lang->value => $lang->label()]),
        ]);
    }

    public function submit(StoreCaregiverApplicationRequest $request)
    {
        $validated = $request->validated();

        $email = Session::get('verified_email');
        if (! $email && app()->environment() !== 'production') {
            $email = 'test-applicant@example.com';
            Session::put('verified_email', $email);
        }

        Log::channel('submission')->info('Application submission started', [
            'email' => $email,
            'experience_count' => count($request->input('experiences', [])),
            'reference_count' => count($request->input('references', [])),
        ]);

        try {
            // Store uploaded files — replace UploadedFile objects with path strings
            if ($photo = $request->file('personal.photo')) {
                try {
                    $manager = new ImageManager(new Driver);
                    $resizedPath = $photo->getRealPath().'.'.($photo->getClientOriginalExtension() ?: 'jpg');
                    $manager->decodePath($photo->getRealPath())->scale(width: 1200)->save($resizedPath);
                    copy($resizedPath, $photo->getRealPath());
                    unlink($resizedPath);
                } catch (\Exception $e) {
                    Log::channel('submission')->warning('Photo resize failed, continuing with original', [
                        'error' => $e->getMessage(),
                    ]);
                }
                $validated['personal']['photo'] = $photo->store('photos', 'public');
            } else {
                unset($validated['personal']['photo']);
            }

            if ($cprCard = $request->file('cpr_card')) {
                // Sensitive background-check document → private disk, reachable
                // only via the authorized certification-document route.
                $validated['cpr_card'] = $cprCard->store('cpr-cards', 'documents');
            } else {
                unset($validated['cpr_card']);
            }

            if ($trustlineUpload = $request->file('trustline_upload')) {
                $validated['trustline_upload'] = $trustlineUpload->store('trustline-uploads', 'documents');
            } else {
                unset($validated['trustline_upload']);
            }

            // Persist the whole application atomically: a failure partway through
            // (e.g. a duplicate reference) must not leave a half-written applicant.
            DB::beginTransaction();

            // Create User
            $user = User::create([
                'name' => "{$validated['personal']['first_name']} {$validated['personal']['last_name']}",
                'email' => $email,
                'password' => bcrypt(Str::random(32)), // Random password
                'role' => 'caregiver',
            ]);

            // Create Caregiver
            $caregiver = Caregiver::create([
                'user_id' => $user->id,
                'status' => CaregiverStatus::Applicant->value,
                'status_token' => Str::random(32),
                'first_name' => $validated['personal']['first_name'],
                'last_name' => $validated['personal']['last_name'],
                'phone' => $validated['personal']['phone'],
                'date_of_birth' => $validated['personal']['dob'],
                'address_line1' => $validated['personal']['address_line1'] ?? null,
                'address_line2' => $validated['personal']['address_line2'] ?? null,
                'address_city' => $validated['personal']['address_city'] ?? null,
                'address_state' => $validated['personal']['address_state'] ?? null,
                'address_zip' => $validated['personal']['address_zip'] ?? null,
            ]);

            // Store education records
            $education = $validated['education'] ?? null;
            if ($education && $education['level'] !== 'high_school') {
                $caregiver->educations()->create([
                    'education_type' => $education['level'],
                    'school_name' => $education['college'] ?? null,
                    'graduation_year' => $education['graduation_year'] ?? null,
                    'degree' => $education['degree'] ?? null,
                ]);
            }
            if ($education && $education['level'] === 'high_school') {
                $caregiver->educations()->create([
                    'education_type' => 'high_school',
                    'school_name' => $education['high_school_name'] ?? null,
                    'graduation_year' => $education['high_school_graduation_year'] ?? null,
                ]);
            }

            // Store certifications (caregiver_certifications pivot)
            if (($validated['cpr_certified'] ?? '') === 'yes') {
                $cprType = CertificationType::where('name', 'CPR')->first();
                if ($cprType) {
                    $caregiver->certifications()->syncWithoutDetaching([
                        $cprType->id => [
                            'expiration_date' => $validated['cpr_expiration'] ?? null,
                            'file_path' => $validated['cpr_card'] ?? null,
                        ],
                    ]);
                }
            }

            if (($validated['trustline_certified'] ?? '') === 'yes') {
                $trustlineType = CertificationType::where('name', 'Trustline')->first();
                if ($trustlineType) {
                    $caregiver->certifications()->syncWithoutDetaching([
                        $trustlineType->id => [
                            'file_path' => $validated['trustline_upload'] ?? null,
                        ],
                    ]);
                }
            }

            // Update Caregiver model columns
            $caregiver->update([
                'education_level' => $education['level'] ?? null,
                'biography' => $validated['bio'] ?? null,
                'languages' => $validated['languages'] ?? null,
                'metadata' => [
                    'smokes' => $validated['smokes'] ?? null,
                    'alcohol' => $validated['alcohol'] ?? null,
                    'substance_abuse' => $validated['substance_abuse'] ?? null,
                    'limitations' => $validated['limitations'] ?? null,
                    'allergic_to_pets' => $validated['allergic_to_pets'] ?? null,
                    'visible_tattoos' => $validated['visible_tattoos'] ?? null,
                    'authorized_to_work' => $validated['authorized_to_work'] ?? null,
                    'reliable_vehicle' => $validated['reliable_vehicle'] ?? null,
                    'cpr_certified' => $validated['cpr_certified'] ?? null,
                    'cpr_expiration' => $validated['cpr_expiration'] ?? null,
                    'trustline_certified' => $validated['trustline_certified'] ?? null,
                    'has_children' => $validated['has_children'] ?? null,
                    'employment_status' => $validated['employment_status'] ?? null,
                    'current_employer' => $validated['current_employer'] ?? null,
                    'things_i_bring' => $validated['things_i_bring'] ?? null,
                    'interests' => $validated['interests'] ?? null,
                    'availability' => $validated['availability'] ?? [],
                    'location_flexible' => $validated['location']['flexible'] ?? false,
                ],
            ]);

            // Sync specialty types (age_groups → specialty_types)
            $ageGroupMap = [
                'babies' => 1,
                'toddlers' => 2,
                'preschool' => 3,
                'school_age' => 4,
            ];
            $specialtyIds = [];
            foreach ($ageGroupMap as $wizardKey => $specialtyTypeId) {
                if (! empty($validated['age_groups'][$wizardKey])) {
                    $specialtyIds[] = $specialtyTypeId;
                }
            }
            if (! empty($specialtyIds)) {
                $existingSpecialtyIds = SpecialtyType::whereIn('id', $specialtyIds)->pluck('id')->toArray();
                if (! empty($existingSpecialtyIds)) {
                    $caregiver->specialtyTypes()->sync($existingSpecialtyIds);
                }
            }

            // Sync locations
            $northSelected = ! empty($validated['location']['north_county']);
            $southSelected = ! empty($validated['location']['south_east_county']);
            $flexible = ! empty($validated['location']['flexible']);
            $locationSync = [];

            if ($flexible) {
                if ($northSelected && $southSelected) {
                    $locationSync[1] = ['is_preferred' => false]; // South
                    $locationSync[2] = ['is_preferred' => false]; // North
                } elseif ($northSelected && ! $southSelected) {
                    $locationSync[1] = ['is_preferred' => false]; // South
                    $locationSync[2] = ['is_preferred' => true]; // North
                } elseif (! $northSelected && $southSelected) {
                    $locationSync[1] = ['is_preferred' => true]; // South
                    $locationSync[2] = ['is_preferred' => false]; // North
                } else {
                    $locationSync[1] = ['is_preferred' => false]; // South
                    $locationSync[2] = ['is_preferred' => false]; // North
                }
            } else {
                if ($northSelected) {
                    $locationSync[2] = ['is_preferred' => false]; // North
                }
                if ($southSelected) {
                    $locationSync[1] = ['is_preferred' => false]; // South
                }
            }
            if (! empty($locationSync)) {
                $existingLocationIds = Location::whereIn('id', array_keys($locationSync))->pluck('id')->toArray();
                if (! empty($existingLocationIds)) {
                    $caregiver->locations()->sync(
                        array_intersect_key($locationSync, array_flip($existingLocationIds))
                    );
                }
            }

            // Sync attributes
            $attributeSync = [];
            if (! empty($validated['position']['petsitting'])) {
                $attributeSync[1] = ['value' => 'true']; // pet_sitting
            }
            if (! empty($validated['qualifications']['driving'])) {
                $attributeSync[3] = ['value' => 'true']; // has_vehicle
            }
            if (($validated['smokes'] ?? '') === 'no') {
                $attributeSync[4] = ['value' => 'true']; // non_smoker
            }
            if (! empty($attributeSync)) {
                $existingAttributeIds = AttributeDefinition::whereIn('id', array_keys($attributeSync))->pluck('id')->toArray();
                if (! empty($existingAttributeIds)) {
                    $caregiver->attributes()->syncWithoutDetaching(
                        array_intersect_key($attributeSync, array_flip($existingAttributeIds))
                    );
                }
            }

            // Store application snapshot
            $application = CaregiverApplication::create([
                'caregiver_id' => $caregiver->id,
                'data' => $validated,
                'submitted_at' => now(),
            ]);

            // Generate generic PDFs (placeholder for now)
            $this->generateAgreements($caregiver, $validated);

            $applicantName = $validated['personal']['first_name'].' '.$validated['personal']['last_name'];

            // Send applicant confirmation email
            Mail::to($email)->queue(new ApplicantConfirmationMail($applicantName, $caregiver->status_token));

            // Send admin notification
            $admins = User::where('role', 'admin')->get();
            Notification::send($admins, new AdminNewApplicationNotification($applicantName, $email, $application->id));

            // Send reference request emails and persist records
            foreach ($validated['references'] as $reference) {
                $token = Str::random(32);
                $referenceEmail = strtolower(trim($reference['email']));
                ReferenceRequest::create([
                    'token' => $token,
                    'caregiver_id' => $caregiver->id,
                    'reference_name' => $reference['first_name'].' '.$reference['last_name'],
                    'reference_email' => $referenceEmail,
                    'relationship' => $reference['relationship'],
                    'years_known' => $reference['years_known'],
                    'is_sponsor' => false,
                ]);
                Mail::to($referenceEmail)->queue(new ReferenceRequestMail(
                    $reference['first_name'].' '.$reference['last_name'],
                    $applicantName,
                    $token,
                ));
            }

            // Send reference request to sponsor
            $sponsorToken = Str::random(32);
            $sponsorEmail = strtolower(trim($validated['sponsor']['email']));
            ReferenceRequest::create([
                'token' => $sponsorToken,
                'caregiver_id' => $caregiver->id,
                'reference_name' => $validated['sponsor']['first_name'].' '.$validated['sponsor']['last_name'],
                'reference_email' => $sponsorEmail,
                'relationship' => $validated['sponsor']['relationship'] ?? null,
                'years_known' => null,
                'is_sponsor' => true,
            ]);
            Mail::to($sponsorEmail)->queue(new ReferenceRequestMail(
                $validated['sponsor']['first_name'].' '.$validated['sponsor']['last_name'],
                $applicantName,
                $sponsorToken,
            ));

            // Clear incomplete application tracking
            IncompleteApplication::where('email', $email)->delete();

            DB::commit();

            // Clear session
            Session::forget(['verified_email', 'verified_at']);

            Log::channel('submission')->info('Application submission completed', [
                'email' => $email,
                'caregiver_id' => $caregiver->id,
                'application_id' => $application->id,
            ]);

            return redirect()->route('caregiver.apply.thank-you');
        } catch (\Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            Log::channel('submission')->error('Application submission failed', [
                'email' => $email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    public function saveProgress(Request $request)
    {
        $email = Session::get('verified_email');

        $request->validate([
            'step' => 'required|integer|min:1|max:8',
            'data' => 'nullable|array',
        ]);

        IncompleteApplication::updateOrCreate(
            ['email' => $email],
            [
                'last_step' => $request->input('step'),
                'draft_data' => $request->input('data'),
                'last_activity_at' => now(),
            ]
        );

        return response()->json(['status' => 'ok']);
    }

    public function thankYou()
    {
        return inertia('public/caregiver-apply/thank-you');
    }

    protected function generateAgreements(Caregiver $caregiver, array $data): void
    {
        $basePath = storage_path("app/agreements/{$caregiver->id}");

        if (! file_exists($basePath)) {
            mkdir($basePath, 0755, true);
        }

        // Generate Verification PDF
        $verificationPdf = Pdf::loadView('pdfs.caregiver-verification', [
            'caregiver' => $caregiver,
            'data' => $data,
        ]);
        $verificationPath = "{$basePath}/verification.pdf";
        file_put_contents($verificationPath, $verificationPdf->output());

        CaregiverAgreement::create([
            'caregiver_id' => $caregiver->id,
            'type' => 'verification',
            'pdf_path' => $verificationPath,
            'signed_at' => now(),
        ]);

        // Generate Agreement PDF
        $agreementPdf = Pdf::loadView('pdfs.caregiver-agreement', [
            'caregiver' => $caregiver,
            'data' => $data,
        ]);
        $agreementPath = "{$basePath}/agreement.pdf";
        file_put_contents($agreementPath, $agreementPdf->output());

        CaregiverAgreement::create([
            'caregiver_id' => $caregiver->id,
            'type' => 'agreement',
            'pdf_path' => $agreementPath,
            'signed_at' => now(),
        ]);
    }

    public function showStatus(string $token)
    {
        $caregiver = Caregiver::where('status_token', $token)->firstOrFail();

        $referenceRequests = $caregiver->referenceRequests()
            ->get()
            ->map(fn ($ref) => [
                'id' => $ref->id,
                'reference_name' => $ref->reference_name,
                'reference_email' => $ref->reference_email,
                'relationship' => $ref->relationship,
                'is_sponsor' => $ref->is_sponsor,
                'is_completed' => $ref->submitted_at !== null,
                'submitted_at' => $ref->submitted_at,
            ]);

        $checklistItems = $caregiver->onboardingChecklistItems()
            ->orderBy('id')
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'label' => $item->label,
                'description' => $item->description,
                'completed' => $item->completed_at !== null,
                'completed_at' => $item->completed_at?->format('Y-m-d H:i:s'),
            ]);

        return Inertia::render('public/caregiver-apply/application-status', [
            'status' => [
                'value' => $caregiver->status->value,
                'label' => $caregiver->status->label(),
                'color' => $caregiver->status->color(),
            ],
            'caregiver_name' => $caregiver->first_name.' '.$caregiver->last_name,
            'reference_requests' => $referenceRequests,
            'checklist_items' => $checklistItems,
            'token' => $token,
        ]);
    }

    public function replaceReference(Request $request, string $token, ReferenceRequest $referenceRequest)
    {
        $caregiver = Caregiver::where('status_token', $token)->firstOrFail();

        abort_if($referenceRequest->caregiver_id !== $caregiver->id, 403);
        abort_if($referenceRequest->submitted_at !== null, 422, 'Cannot replace a completed reference.');

        $validated = $request->validate([
            'reference_name' => 'required|string|max:255',
            'reference_email' => 'required|email|max:255',
            'relationship' => 'nullable|string|max:255',
        ]);

        $newToken = Str::random(32);
        $newEmail = strtolower(trim($validated['reference_email']));

        $referenceRequest->update([
            'reference_name' => $validated['reference_name'],
            'reference_email' => $newEmail,
            'relationship' => $validated['relationship'] ?? $referenceRequest->relationship,
            'token' => $newToken,
            'submitted_at' => null,
            'rating_reliability' => null,
            'rating_trustworthiness' => null,
            'rating_maturity' => null,
            'rating_communication' => null,
            'rating_warmth' => null,
            'rating_overall_recommendation' => null,
            'strengths' => null,
            'concerns' => null,
            'additional_comments' => null,
        ]);

        Mail::to($newEmail)->queue(new ReferenceRequestMail(
            $validated['reference_name'],
            $caregiver->first_name.' '.$caregiver->last_name,
            $newToken,
        ));

        return redirect()->back();
    }
}
