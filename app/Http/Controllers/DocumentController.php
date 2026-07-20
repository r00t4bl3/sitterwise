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

        abort_if(! $path, 404);

        // New rows: relative path on the private documents disk.
        if (Storage::disk('documents')->exists($path)) {
            return Storage::disk('documents')->response($path);
        }

        // Legacy rows stored an absolute filesystem path via file_put_contents.
        // The baked-in path may point at an old deployment directory (domain or
        // path rename), so try this deployment's storage location first.
        if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            $relative = "agreements/{$caregiver->id}/".basename($path);
            $current = storage_path('app/'.$relative);

            foreach ([$current, $path] as $candidate) {
                if (is_file($candidate)) {
                    return response()->file($candidate);
                }
            }
        }

        abort(404);
    }
}
