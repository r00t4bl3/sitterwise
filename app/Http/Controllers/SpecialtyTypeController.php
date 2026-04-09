<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSpecialtyTypeRequest;
use App\Http\Requests\UpdateSpecialtyTypeRequest;
use App\Models\SpecialtyType;
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

    public function store(StoreSpecialtyTypeRequest $request)
    {
        $validated = $request->validated();

        $maxOrder = SpecialtyType::max('sort_order') ?? 0;
        $validated['sort_order'] = $maxOrder + 1;

        SpecialtyType::create($validated);

        return redirect()->route('specialties.index')
            ->with('success', 'Specialty created successfully');
    }

    public function update(UpdateSpecialtyTypeRequest $request, SpecialtyType $specialty)
    {
        $validated = $request->validated();

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
