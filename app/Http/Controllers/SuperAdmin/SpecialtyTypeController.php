<?php
namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SpecialtyType;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SpecialtyTypeController extends Controller
{
    public function index()
    {
        $specialties = SpecialtyType::orderBy('sort_order')->orderBy('name')->get();

        return Inertia::render('superadmin/specialties/index', [
            'specialties' => $specialties,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|unique:specialty_types,name',
            'description' => 'nullable|string',
        ]);

        $maxOrder                = SpecialtyType::max('sort_order') ?? 0;
        $validated['sort_order'] = $maxOrder + 1;

        SpecialtyType::create($validated);

        return redirect()->route('specialties.index')
            ->with('success', 'Specialty created successfully');
    }

    public function update(Request $request, SpecialtyType $specialty)
    {
        $validated = $request->validate([
            'name'        => 'required|string|unique:specialty_types,name,' . $specialty->id,
            'description' => 'nullable|string',
            'is_active'   => 'boolean',
            'sort_order'  => 'integer',
        ]);

        $specialty->update($validated);

        return redirect()->route('specialties.index')
            ->with('success', 'Specialty updated successfully');
    }

    public function destroy(SpecialtyType $specialty)
    {
        $specialty->delete();

        return redirect()->route('specialties.index')
            ->with('success', 'Specialty deleted successfully');
    }
}
