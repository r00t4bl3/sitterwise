<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCertificationTypeRequest;
use App\Http\Requests\UpdateCertificationTypeRequest;
use App\Models\CertificationType;
use Inertia\Inertia;

class CertificationTypeController extends Controller
{
    public function index()
    {
        $certifications = CertificationType::orderBy('name')->get();

        return Inertia::render('superadmin/certifications/index', [
            'certifications' => $certifications,
        ]);
    }

    public function store(StoreCertificationTypeRequest $request)
    {
        $validated = $request->validated();

        CertificationType::create($validated);

        return redirect()->route('certifications.index')
            ->with('success', 'Certification created successfully');
    }

    public function update(UpdateCertificationTypeRequest $request, CertificationType $certification)
    {
        $validated = $request->validated();

        $certification->update($validated);

        return redirect()->route('certifications.index')
            ->with('success', 'Certification updated successfully');
    }

    public function destroy(CertificationType $certification)
    {
        $certification->delete();

        return redirect()->route('certifications.index')
            ->with('success', 'Certification deleted successfully');
    }
}
