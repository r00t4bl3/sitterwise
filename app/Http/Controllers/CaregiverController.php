<?php

namespace App\Http\Controllers;

use App\Models\AttributeDefinition;
use App\Models\Caregiver;
use App\Models\CaregiverStatus;
use App\Models\CertificationType;
use App\Models\Location;
use App\Models\SpecialtyType;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CaregiverController extends Controller
{
    public function index(Request $request)
    {
        $query = Caregiver::with(['status', 'specialtyTypes', 'locations']);

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        if ($request->has('status') && $request->status) {
            $query->where('status_id', $request->status);
        }

        $caregivers = $query->orderBy('last_name')->paginate(20);
        $statuses = CaregiverStatus::active()->orderBy('sort_order')->get();

        return Inertia::render('caregivers/index', [
            'caregivers' => $caregivers,
            'statuses' => $statuses,
            'filters' => [
                'search' => $request->search,
                'status' => $request->status,
            ],
        ]);
    }

    public function show(Caregiver $caregiver)
    {
        $caregiver->load(['status', 'specialtyTypes', 'user']);

        $caregiver->load(['locations' => function ($query) {
            $query->withPivot('is_preferred');
        }]);

        $caregiver->load(['certifications' => function ($query) {
            $query->withPivot('expiration_date', 'verified_at');
        }]);

        $caregiver->load(['attributes' => function ($query) {
            $query->withPivot('value');
        }]);

        $statuses = CaregiverStatus::active()->orderBy('sort_order')->get();

        $formattedDob = $caregiver->date_of_birth
            ? Carbon::parse($caregiver->date_of_birth)->format('F j, Y')
            : null;

        return Inertia::render('caregivers/show', [
            'caregiver' => [
                'id' => $caregiver->id,
                'first_name' => $caregiver->first_name,
                'last_name' => $caregiver->last_name,
                'email' => $caregiver->user?->email,
                'phone' => $caregiver->phone,
                'address' => $caregiver->address,
                'date_of_birth' => $formattedDob,
                'date_of_birth_raw' => $caregiver->date_of_birth,
                'profile_photo_path' => $caregiver->profile_photo_path,
                'rating' => $caregiver->rating,
                'biography' => $caregiver->biography,
                'notes' => $caregiver->notes,
                'status' => $caregiver->status,
                'specialty_types' => $caregiver->specialtyTypes,
                'locations' => $caregiver->locations->map(fn ($l) => [
                    'id' => $l->id,
                    'name' => $l->name,
                    'is_preferred' => (bool) $l->pivot->is_preferred,
                ]),
                'certifications' => $caregiver->certifications->map(fn ($c) => [
                    'id' => $c->id,
                    'certification_type' => [
                        'id' => $c->id,
                        'name' => $c->name,
                    ],
                    'expiration_date' => $c->pivot->expiration_date,
                    'verified_at' => $c->pivot->verified_at,
                ]),
                'attributes' => $caregiver->attributes->map(fn ($a) => [
                    'id' => $a->id,
                    'attribute_definition' => [
                        'id' => $a->id,
                        'name' => $a->name,
                        'slug' => $a->slug,
                    ],
                    'value' => $a->pivot->value,
                ]),
            ],
            'statuses' => $statuses,
            'csrf_token' => csrf_token(),
        ]);
    }

    public function update(Request $request, Caregiver $caregiver)
    {
        $rules = [
            'status_id' => 'required|exists:caregiver_statuses,id',
        ];

        if ($request->has('first_name')) {
            $rules['first_name'] = 'required|string|max:255';
            $rules['last_name'] = 'required|string|max:255';
            $rules['phone'] = 'nullable|string|max:255';
            $rules['address'] = 'nullable|string|max:500';
            $rules['date_of_birth'] = 'nullable|date';
            $rules['rating'] = 'nullable|numeric|min:0|max:5';
            $rules['biography'] = 'nullable|string';
            $rules['notes'] = 'nullable|string';
            $rules['profile_photo'] = 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048';
            $rules['specialty_type_ids'] = 'nullable|array';
            $rules['specialty_type_ids.*'] = 'exists:specialty_types,id';
            $rules['location_ids'] = 'nullable|array';
            $rules['location_ids.*'] = 'exists:locations,id';
            $rules['preferred_location_id'] = 'nullable|exists:locations,id';
            $rules['attribute_values'] = 'nullable|array';
            $rules['attribute_values.*'] = 'nullable|string';
            $rules['certifications'] = 'nullable|array';
            $rules['certifications.*.certification_type_id'] = 'required|exists:certification_types,id';
            $rules['certifications.*.expiration_date'] = 'nullable|date';
            $rules['certifications.*.verified_at'] = 'nullable|date';
        }

        $validated = $request->validate($rules);

        if ($request->has('first_name')) {
            $updateData = [
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'phone' => $validated['phone'] ?? null,
                'address' => $validated['address'] ?? null,
                'date_of_birth' => $validated['date_of_birth'] ?? null,
                'rating' => $validated['rating'] ?? null,
                'biography' => $validated['biography'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'status_id' => $validated['status_id'],
            ];

            if ($request->hasFile('profile_photo')) {
                $file = $request->file('profile_photo');
                $filename = time().'_'.$file->getClientOriginalName();
                $path = $file->storeAs('profile-photos', $filename, 'public');
                $updateData['profile_photo_path'] = $path;
            }

            $caregiver->update($updateData);

            if (isset($validated['specialty_type_ids'])) {
                $caregiver->specialtyTypes()->sync($validated['specialty_type_ids']);
            }

            if (isset($validated['location_ids'])) {
                $locationSync = [];
                foreach ($validated['location_ids'] as $locationId) {
                    $locationSync[$locationId] = [
                        'is_preferred' => $locationId == ($validated['preferred_location_id'] ?? null),
                    ];
                }
                $caregiver->locations()->sync($locationSync);
            }

            if (isset($validated['attribute_values'])) {
                $attributeSync = [];
                foreach ($validated['attribute_values'] as $attributeId => $value) {
                    if ($value === 'true' || $value === '1') {
                        $attributeSync[$attributeId] = ['value' => 'true'];
                    }
                }
                $caregiver->attributes()->sync($attributeSync);
            }

            if (isset($validated['certifications'])) {
                $certSync = [];
                foreach ($validated['certifications'] as $cert) {
                    $certSync[$cert['certification_type_id']] = [
                        'expiration_date' => $cert['expiration_date'] ?? null,
                        'verified_at' => $cert['verified_at'] ?? null,
                    ];
                }
                $caregiver->certifications()->sync($certSync);
            }
        } else {
            $caregiver->update(['status_id' => $validated['status_id']]);
        }

        return redirect()->route('caregivers.show', $caregiver->id)
            ->with('success', 'Caregiver updated successfully');
    }

    public function edit(Caregiver $caregiver)
    {
        $caregiver->load(['status', 'specialtyTypes', 'locations', 'user']);

        $caregiver->load(['certifications' => function ($query) {
            $query->withPivot('expiration_date', 'verified_at');
        }]);

        $caregiver->load(['attributes' => function ($query) {
            $query->withPivot('value');
        }]);

        $statuses = CaregiverStatus::active()->orderBy('sort_order')->get();
        $specialtyTypes = SpecialtyType::active()->get();
        $locations = Location::active()->get();
        $attributeDefinitions = AttributeDefinition::active()->get();
        $certificationTypes = CertificationType::active()->get();

        return Inertia::render('caregivers/edit', [
            'caregiver' => [
                'id' => $caregiver->id,
                'first_name' => $caregiver->first_name,
                'last_name' => $caregiver->last_name,
                'email' => $caregiver->user?->email,
                'phone' => $caregiver->phone,
                'address' => $caregiver->address,
                'date_of_birth' => $caregiver->date_of_birth,
                'profile_photo_path' => $caregiver->profile_photo_path,
                'rating' => $caregiver->rating,
                'biography' => $caregiver->biography,
                'notes' => $caregiver->notes,
                'status_id' => $caregiver->status_id,
                'specialty_type_ids' => $caregiver->specialtyTypes->pluck('id')->toArray(),
                'location_ids' => $caregiver->locations->pluck('id')->toArray(),
                'preferred_location_id' => $caregiver->locations()->wherePivot('is_preferred', true)->first()?->id,
                'attributes' => $caregiver->attributes->map(fn ($a) => [
                    'id' => $a->id,
                    'attribute_definition_id' => $a->attribute_definition_id,
                    'name' => $a->name,
                    'slug' => $a->slug,
                    'value' => $a->pivot->value,
                ]),
                'certifications' => $caregiver->certifications->map(fn ($c) => [
                    'id' => $c->pivot->id,
                    'certification_type_id' => $c->pivot->certification_type_id,
                    'certification_type' => [
                        'id' => $c->id,
                        'name' => $c->name,
                    ],
                    'expiration_date' => $c->pivot->expiration_date,
                    'verified_at' => $c->pivot->verified_at,
                ]),
            ],
            'statuses' => $statuses,
            'specialty_types' => $specialtyTypes,
            'locations' => $locations,
            'attribute_definitions' => $attributeDefinitions,
            'certification_types' => $certificationTypes,
            'csrf_token' => csrf_token(),
        ]);
    }
}
