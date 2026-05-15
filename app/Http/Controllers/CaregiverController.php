<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Enums\LocationType;
use App\Enums\ServiceType;
use App\Http\Requests\ResetCaregiverPasswordRequest;
use App\Http\Requests\StoreCaregiverRequest;
use App\Http\Requests\UpdateCaregiverProfilePhotoRequest;
use App\Http\Requests\UpdateCaregiverRequest;
use App\Http\Resources\CaregiverResource;
use App\Models\AttributeDefinition;
use App\Models\Booking;
use App\Models\Caregiver;
use App\Models\CaregiverStatus;
use App\Models\CertificationType;
use App\Models\Location;
use App\Models\SpecialtyType;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class CaregiverController extends Controller
{
    public function index(Request $request)
    {
        $query = Caregiver::with(['user', 'status', 'specialtyTypes', 'locations', 'certifications']);

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        if ($request->has('status') && $request->status && $request->status !== 'all') {
            $query->where('status_id', $request->status);
        }

        $caregivers = $query->orderByDesc('id')->paginate(20)->appends($request->query());
        $statuses = CaregiverStatus::active()->orderBy('sort_order')->get();

        return Inertia::render('admin/caregivers/index', [
            'caregivers' => $caregivers,
            'statuses' => $statuses,
            'filters' => [
                'search' => $request->search,
                'status' => $request->status ?? 'all',
            ],
        ]);
    }

    public function create()
    {
        $statuses = CaregiverStatus::active()->orderBy('sort_order')->get();

        return Inertia::render('admin/caregivers/create', [
            'statuses' => $statuses,
        ]);
    }

    public function store(StoreCaregiverRequest $request)
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['first_name'].' '.$validated['last_name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'caregiver',
        ]);

        $caregiver = Caregiver::create([
            'user_id' => $user->id,
            'status_id' => $validated['status_id'],
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'biography' => $validated['biography'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('caregivers.show', $caregiver->id)
            ->with('success', 'Caregiver created successfully');
    }

    public function searchSuggestions(Request $request)
    {
        $query = Caregiver::with(['user', 'status']);

        if ($request->has('q') && $request->q) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        $caregivers = $query->orderBy('last_name')->limit(6)->get(['id', 'first_name', 'last_name', 'rating', 'status_id']);

        return response()->json($caregivers);
    }

    public function show(Caregiver $caregiver)
    {
        $caregiver->load(['status', 'specialtyTypes', 'user', 'locations', 'certifications', 'attributes', 'applications', 'agreements']);

        $statuses = CaregiverStatus::active()->orderBy('sort_order')->get();

        return Inertia::render('admin/caregivers/show', [
            'caregiver' => (new CaregiverResource($caregiver))->resolve(),
            'statuses' => $statuses,
        ]);
    }

    public function jobHistory(Request $request, Caregiver $caregiver)
    {
        $query = Booking::with(['client.user', 'hotel'])
            ->where('caregiver_id', $caregiver->id);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('client.user', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('hotel', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                    ->orWhere('location_type', 'like', "%{$search}%");
            });
        }

        $bookings = $query->orderBy('start_datetime', 'desc')
            ->paginate(20)
            ->appends($request->query());

        $bookingStatuses = array_map(
            fn ($case) => [
                'value' => $case->value,
                'label' => $case->label(),
                'colors' => $case->colors(),
            ],
            BookingStatus::cases()
        );

        $serviceTypes = array_map(
            fn ($case) => ['value' => $case->value, 'label' => $case->label()],
            ServiceType::cases()
        );

        return Inertia::render('admin/caregivers/job-history', [
            'caregiver' => [
                'id' => $caregiver->id,
                'first_name' => $caregiver->first_name,
                'last_name' => $caregiver->last_name,
            ],
            'bookings' => $bookings,
            'bookingStatuses' => $bookingStatuses,
            'serviceTypes' => $serviceTypes,
            'locationTypes' => array_map(
                fn ($case) => ['value' => $case->value, 'label' => $case->label()],
                LocationType::cases()
            ),
            'filters' => [
                'search' => $request->search,
                'status' => $request->status,
            ],
        ]);
    }

    public function publicBio(string $slug)
    {
        $caregiver = Caregiver::where('slug', $slug)->with('user')->firstOrFail();

        return Inertia::render('public/caregiver-bio', [
            'caregiver' => (new CaregiverResource($caregiver))->resolve(),
        ]);
    }

    public function update(UpdateCaregiverRequest $request, Caregiver $caregiver): RedirectResponse
    {
        $validated = $request->validated();

        if ($request->has('first_name')) {
            $addressLine1 = $validated['address_line1'] ?? null;
            $addressLine2 = $validated['address_line2'] ?? null;
            $addressCity = $validated['address_city'] ?? null;
            $addressState = $validated['address_state'] ?? null;
            $addressZip = $validated['address_zip'] ?? null;

            $fullAddress = implode(', ', array_filter([
                $addressLine1,
                $addressLine2,
                $addressCity,
                $addressState,
                $addressZip,
            ]));

            $updateData = [
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'phone' => $validated['phone'] ?? null,
                'address_line1' => $addressLine1,
                'address_line2' => $addressLine2,
                'address_city' => $addressCity,
                'address_state' => $addressState,
                'address_zip' => $addressZip,
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
                $caregiver->user->update(['profile_photo_path' => $path]);
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
                $caregiver->attributes()->syncWithoutDetaching($attributeSync);
            }

            if (isset($validated['certifications'])) {
                $certSync = [];
                $certFiles = $request->file('cert_files') ?? [];

                foreach ($validated['certifications'] as $cert) {
                    $certTypeId = $cert['certification_type_id'];
                    $filePath = $cert['file_path'] ?? null;

                    if (isset($certFiles[$certTypeId])) {
                        $file = $certFiles[$certTypeId];
                        $filename = time().'_'.$file->getClientOriginalName();
                        $filePath = $file->storeAs('certifications', $filename, 'public');
                    }

                    $certSync[$certTypeId] = [
                        'expiration_date' => $cert['expiration_date'] ?? null,
                        'verified_at' => $cert['verified_at'] ?? null,
                        'file_path' => $filePath,
                        'notes' => $cert['notes'] ?? null,
                    ];
                }
                $caregiver->certifications()->sync($certSync);
            }

            if (isset($validated['educations'])) {
                $caregiver->educations()->delete();
                foreach ($validated['educations'] as $edu) {
                    if (! empty($edu['school_name'])) {
                        $caregiver->educations()->create([
                            'education_type' => $edu['education_type'],
                            'school_name' => $edu['school_name'],
                            'graduation_year' => $edu['graduation_year'] ?? null,
                        ]);
                    }
                }
            }
        } else {
            $caregiver->update(['status_id' => $validated['status_id']]);
        }

        return redirect()->route('caregivers.show', $caregiver->id)
            ->with('success', 'Caregiver updated successfully');
    }

    public function edit(Caregiver $caregiver)
    {
        $caregiver->load(['status', 'specialtyTypes', 'locations', 'user', 'certifications', 'attributes', 'educations']);

        $statuses = CaregiverStatus::active()->orderBy('sort_order')->get();
        $specialtyTypes = SpecialtyType::active()->get();
        $locations = Location::active()->get();
        $attributeDefinitions = AttributeDefinition::active()->forCaregivers()->get();
        $certificationTypes = CertificationType::active()->get();

        return Inertia::render('admin/caregivers/edit', [
            'caregiver' => (new CaregiverResource($caregiver))->resolve(),
            'statuses' => $statuses,
            'specialty_types' => $specialtyTypes,
            'locations' => $locations,
            'attribute_definitions' => $attributeDefinitions,
            'certification_types' => $certificationTypes,
            'csrf_token' => csrf_token(),
        ]);
    }

    public function updateProfilePhoto(UpdateCaregiverProfilePhotoRequest $request, Caregiver $caregiver): RedirectResponse|JsonResponse
    {
        $file = $request->file('profile_photo');
        $filename = time().'_'.$file->getClientOriginalName();
        $path = $file->storeAs('profile-photos', $filename, 'public');
        $caregiver->user->update(['profile_photo_path' => $path]);

        return redirect()->route('caregivers.edit', $caregiver->id)
            ->with('success', 'Profile photo updated successfully');
    }

    public function resetPassword(ResetCaregiverPasswordRequest $request, Caregiver $caregiver): RedirectResponse
    {
        $validated = $request->validated();

        if (! $caregiver->user) {
            return redirect()->route('caregivers.show', $caregiver->id)
                ->with('error', 'Caregiver does not have a user account');
        }

        $caregiver->user->update([
            'password' => Hash::make($validated['new_password']),
        ]);

        return redirect()->route('caregivers.show', $caregiver->id)
            ->with('success', 'Password has been reset successfully');
    }

    public function updateAdminRating(Request $request, Caregiver $caregiver): RedirectResponse
    {
        $validated = $request->validate([
            'admin_rating' => 'required|numeric|min:1|max:5',
        ]);

        $caregiver->update([
            'admin_rating' => round($validated['admin_rating'], 2),
        ]);

        return back()->with('success', 'Admin rating updated successfully');
    }
}
