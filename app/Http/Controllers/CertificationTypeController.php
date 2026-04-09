<?php

namespace App\Http\Controllers;

use App\Models\CertificationType;
use Illuminate\Http\Request;
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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:certification_types,name',
            'description' => 'nullable|string',
            'expires_required' => 'boolean',
        ]);

        CertificationType::create($validated);

        return redirect()->route('certifications.index')
            ->with('success', 'Certification created successfully');
    }

    public function update(Request $request, CertificationType $certification)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:certification_types,name,'.$certification->id,
            'description' => 'nullable|string',
            'expires_required' => 'boolean',
            'is_active' => 'boolean',
        ]);

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
