<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCaregiverApplicationRequest;
use App\Mail\AdminNewApplicationMail;
use App\Mail\ApplicantConfirmationMail;
use App\Mail\ReferenceRequestMail;
use App\Models\Caregiver;
use App\Models\CaregiverAgreement;
use App\Models\CaregiverApplication;
use App\Models\CaregiverStatus;
use App\Models\ReferenceRequest;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
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
            Session::put('verified_email', $email);
            Session::put('verified_at', now());

            return redirect()->route('caregiver.apply');
        }

        $storedOtp = Cache::get("otp_{$email}");

        if (! $storedOtp || $storedOtp !== $otp) {
            return back()->withErrors(['otp' => 'Invalid verification code. Please try again.']);
        }

        // Store verified email in session
        Session::put('verified_email', $email);
        Session::put('verified_at', now());

        // Clear OTP from cache
        Cache::forget("otp_{$email}");

        return redirect()->route('caregiver.apply');
    }

    public function showWizard()
    {
        return inertia('public/caregiver-apply/wizard');
    }

    public function submit(StoreCaregiverApplicationRequest $request)
    {
        $validated = $request->validated();

        // Store uploaded files — replace UploadedFile objects with path strings
        if ($photo = $request->file('personal.photo')) {
            try {
                $manager = new ImageManager(new Driver);
                $manager->read($photo->getRealPath())->scale(width: 1200)->save($photo->getRealPath());
            } catch (\Exception $e) {
                // Degrade gracefully if image processing fails
            }
            $validated['personal']['photo'] = $photo->store('photos', 'public');
        } else {
            unset($validated['personal']['photo']);
        }

        if ($cprCard = $request->file('cpr_card')) {
            $validated['cpr_card'] = $cprCard->store('cpr-cards', 'public');
        } else {
            unset($validated['cpr_card']);
        }

        if ($trustlineUpload = $request->file('trustline_upload')) {
            $validated['trustline_upload'] = $trustlineUpload->store('trustline-uploads', 'public');
        } else {
            unset($validated['trustline_upload']);
        }

        // Get email from session or use test email in non-production
        $email = Session::get('verified_email');
        if (! $email && app()->environment() !== 'production') {
            // Use a test email for non-production environments
            $email = 'test-applicant@example.com';
            Session::put('verified_email', $email);
        }

        // Create User
        $user = User::create([
            'name' => "{$validated['personal']['first_name']} {$validated['personal']['last_name']}",
            'email' => $email,
            'password' => bcrypt(Str::random(32)), // Random password
            'role' => 'caregiver',
        ]);

        // Get applicant status
        $applicantStatus = CaregiverStatus::where('name', 'applicant')->first();
        $applicantStatusId = $applicantStatus ? $applicantStatus->id : null;

        // Create Caregiver
        $caregiver = Caregiver::create([
            'user_id' => $user->id,
            'status_id' => $applicantStatusId,
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
        Mail::to($email)->queue(new ApplicantConfirmationMail($applicantName));

        // Send admin notification
        $adminEmails = User::whereIn('role', ['admin', 'super_admin'])->pluck('email');
        foreach ($adminEmails as $adminEmail) {
            Mail::to($adminEmail)->queue(new AdminNewApplicationMail($applicantName, $email));
        }

        // Send reference request emails and persist records
        foreach ($validated['references'] as $reference) {
            $token = Str::random(32);
            ReferenceRequest::create([
                'token' => $token,
                'caregiver_id' => $caregiver->id,
                'reference_name' => $reference['first_name'].' '.$reference['last_name'],
                'reference_email' => $reference['email'],
                'relationship' => $reference['relationship'],
                'years_known' => $reference['years_known'],
                'is_sponsor' => false,
            ]);
            Mail::to($reference['email'])->queue(new ReferenceRequestMail(
                $reference['first_name'].' '.$reference['last_name'],
                $applicantName,
                $token,
            ));
        }

        // Send reference request to sponsor
        $sponsorToken = Str::random(32);
        ReferenceRequest::create([
            'token' => $sponsorToken,
            'caregiver_id' => $caregiver->id,
            'reference_name' => $validated['sponsor']['first_name'].' '.$validated['sponsor']['last_name'],
            'reference_email' => $validated['sponsor']['email'],
            'relationship' => $validated['sponsor']['relationship'] ?? null,
            'years_known' => null,
            'is_sponsor' => true,
        ]);
        Mail::to($validated['sponsor']['email'])->queue(new ReferenceRequestMail(
            $validated['sponsor']['first_name'].' '.$validated['sponsor']['last_name'],
            $applicantName,
            $sponsorToken,
        ));

        // Clear session
        Session::forget(['verified_email', 'verified_at']);

        return redirect()->route('caregiver.apply.thank-you');
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
}
