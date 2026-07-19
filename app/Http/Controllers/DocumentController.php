<?php

namespace App\Http\Controllers;

use App\Models\Caregiver;
use App\Models\CaregiverAgreement;
use App\Models\CertificationType;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    /**
     * Stream a caregiver's certification document (CPR card, TrustLine upload,
     * or other certification file) from the private disk. Route middleware
     * restricts this to admin/super_admin; the files are never web-served.
     */
    public function certificationDocument(Caregiver $caregiver, CertificationType $certificationType): StreamedResponse
    {
        $certification = $caregiver->certifications()
            ->where('certification_type_id', $certificationType->id)
            ->first();

        $path = $certification?->pivot->file_path;

        abort_if(! $path || ! Storage::disk('documents')->exists($path), 404);

        return Storage::disk('documents')->response($path);
    }

    /**
     * Stream a caregiver's signed agreement PDF. Staff-only via route
     * middleware. Handles both new relative paths on the documents disk and
     * legacy absolute paths from before the migration, so downloads keep
     * working during the transition window.
     */
    public function caregiverAgreement(Caregiver $caregiver, CaregiverAgreement $agreement): StreamedResponse|BinaryFileResponse
    {
        abort_if($agreement->caregiver_id !== $caregiver->id, 404);

        $path = $agreement->pdf_path;

        // Legacy rows stored an absolute filesystem path via file_put_contents.
        if ($path && str_starts_with($path, DIRECTORY_SEPARATOR) && is_file($path)) {
            return response()->file($path);
        }

        abort_if(! $path || ! Storage::disk('documents')->exists($path), 404);

        return Storage::disk('documents')->response($path);
    }
}
