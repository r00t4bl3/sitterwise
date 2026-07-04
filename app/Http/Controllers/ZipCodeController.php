<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreZipCodeRequest;
use App\Http\Requests\UpdateZipCodeRequest;
use App\Models\ZipCode;
use App\Services\CaregiverRecommendation\LocationMatcher;
use Illuminate\Http\RedirectResponse;

class ZipCodeController extends Controller
{
    public function __construct(
        protected LocationMatcher $locationMatcher,
    ) {}

    public function store(StoreZipCodeRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $existing = ZipCode::with('location:id,name')
            ->where('zip_code', $validated['zip_code'])
            ->first();

        if ($existing) {
            $region = $existing->location?->name ?? 'no region';

            return back()->withErrors([
                'zip_code' => "Zip {$existing->zip_code} is already assigned to {$region}.",
            ]);
        }

        ZipCode::create($validated);
        $this->locationMatcher->flush();

        return back()->with('success', 'Zip code added.');
    }

    public function update(UpdateZipCodeRequest $request, ZipCode $zipCode): RedirectResponse
    {
        $zipCode->update($request->validated());
        $this->locationMatcher->flush();

        return back()->with('success', 'Zip code updated.');
    }

    public function destroy(ZipCode $zipCode): RedirectResponse
    {
        $zipCode->delete();
        $this->locationMatcher->flush();

        return back()->with('success', 'Zip code removed.');
    }
}
