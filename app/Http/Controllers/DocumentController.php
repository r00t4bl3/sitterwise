<?php

namespace App\Http\Controllers;

use App\Models\Caregiver;
use App\Models\CertificationType;
use Illuminate\Support\Facades\Storage;
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
}
