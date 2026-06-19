<?php

namespace App\Http\Controllers;

use App\Console\Commands\RecalculateReliability;
use App\Enums\AssignmentResolution;
use App\Enums\BookingStatus;
use App\Enums\CaregiverStatus;
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
use App\Models\CaregiverAssignment;
use App\Models\CertificationType;
use App\Models\Location;
use App\Models\SpecialtyType;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class CaregiverController extends Controller
{
    private const SORTABLE_COLUMNS = [
        'id', 'first_name', 'last_name', 'rating', 'date_of_birth',
    ];

    public function index(Request $request)
    {
        $query = Caregiver::with(['user', 'specialtyTypes', 'locations', 'certifications']);

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        if ($request->has('status') && $request->status && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $sort = in_array($request->sort, self::SORTABLE_COLUMNS, true) ? $request->sort : 'id';
        $direction = $request->direction ?? 'asc';
        if (! in_array(strtolower($direction), ['asc', 'desc'], true)) {
            $direction = 'asc';
        }

        $caregivers = $query->orderBy($sort, $direction)->paginate(20)->appends($request->query());
        $statuses = array_map(fn ($case) => [
            'value' => $case->value,
            'label' => $case->label(),
            'color' => $case->color(),
        ], CaregiverStatus::cases());

        return Inertia::render('admin/caregivers/index', [
            'caregivers' => $caregivers,
            'statuses' => $statuses,
            'filters' => [
                'search' => $request->search,
                'status' => $request->status ?? 'all',
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    public function create()
    {
        $statuses = array_map(fn ($case) => [
            'value' => $case->value,
            'label' => $case->label(),
            'color' => $case->color(),
        ], CaregiverStatus::cases());

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
            'status' => $validated['status'],
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
        $query = Caregiver::with(['user']);

        if ($request->has('q') && $request->q) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        $caregivers = $query->orderBy('last_name')->limit(6)->get(['id', 'first_name', 'last_name', 'rating', 'status']);

        return response()->json($caregivers);
    }

    public function show(Caregiver $caregiver)
    {
        $caregiver->load(['specialtyTypes', 'user', 'locations', 'certifications', 'attributes', 'application', 'agreements', 'referenceRequests', 'internalRating']);

        $statuses = array_map(fn ($case) => [
            'value' => $case->value,
            'label' => $case->label(),
            'color' => $case->color(),
        ], CaregiverStatus::cases());

        return Inertia::render('admin/caregivers/show', [
            'caregiver' => (new CaregiverResource($caregiver))->resolve(),
            'statuses' => $statuses,
            'activePause' => $caregiver->activePause()->first() ? [
                'paused_at' => $caregiver->activePause->paused_at?->format('Y-m-d'),
                'resume_by' => $caregiver->activePause->resume_by?->format('Y-m-d'),
                'pause_reason' => $caregiver->activePause->pause_reason,
            ] : null,
            'reviews' => Inertia::defer(fn () => $caregiver->receivedRatings()
                ->with(['rater', 'booking.client'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(fn ($r) => [
                    'id' => $r->id,
                    'rating' => (float) $r->rating,
                    'comment' => $r->comment,
                    'rater_name' => $r->rater?->name,
                    'booking_service' => $r->booking?->service_type,
                    'client_name' => $r->booking?->client?->full_name,
                    'created_at' => $r->created_at?->format('Y-m-d'),
                ]),
            ),
            'jobHistory' => Inertia::defer(fn () => CaregiverAssignment::with(['booking.client', 'booking.hotel'])
                ->where('caregiver_id', $caregiver->id)
                ->orderBy(
                    Booking::select('start_datetime')
                        ->whereColumn('id', 'caregiver_assignments.booking_id'),
                    'desc'
                )
                ->take(20)
                ->get()
                ->map(fn ($assignment) => [
                    'id' => $assignment->id,
                    'job_number' => '#'.$assignment->booking->id,
                    'date' => $assignment->booking->start_datetime?->format('Y-m-d\TH:i:s\Z'),
                    'client_id' => $assignment->booking->client_id,
                    'client_name' => $assignment->booking->client?->full_name ?? '—',
                    'client_description' => $assignment->booking->hotel?->name
                        ?? $assignment->booking->address_city
                        ?? '—',
                    'resolution' => $assignment->resolution,
                    'resolution_label' => $assignment->resolution
                        ? AssignmentResolution::tryFrom($assignment->resolution)?->label()
                        : 'Pending',
                    'resolution_color' => $assignment->resolution
                        ? AssignmentResolution::tryFrom($assignment->resolution)?->color()
                        : '#6B7280',
                    'resolution_note' => $assignment->resolution_note,
                    'late_arrival' => $assignment->late_arrival_flag,
                ]),
            ),
        ]);
    }

    public function jobHistory(Request $request, Caregiver $caregiver)
    {
        $query = Booking::with(['client.user', 'hotel', 'assignments'])
            ->where('caregiver_id', $caregiver->id);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $terms = array_filter(explode(' ', $search));
            $query->where(function ($q) use ($terms) {
                $q->whereHas('client', function ($cq) use ($terms) {
                    foreach ($terms as $term) {
                        $cq->where(function ($q) use ($term) {
                            $q->where('first_name', 'like', "%{$term}%")
                                ->orWhere('last_name', 'like', "%{$term}%");
                        });
                    }
                })
                    ->orWhereHas('hotel', fn ($q) => $q->where('name', 'like', '%'.implode(' ', $terms).'%'))
                    ->orWhere('location_type', 'like', '%'.implode(' ', $terms).'%');
            });
        }

        $bookings = $query->orderBy('start_datetime', 'desc')
            ->paginate(20)
            ->appends($request->query());

        $bookings->getCollection()->transform(fn ($booking) => [
            ...$booking->toArray(),
            'assignment_id' => $booking->assignments->first()?->id,
            'assignment_resolution' => $booking->assignments->first()?->resolution,
            'assignment_resolution_label' => $booking->assignments->first()?->resolution
                ? AssignmentResolution::tryFrom($booking->assignments->first()->resolution)?->label()
                : null,
            'assignment_resolution_color' => $booking->assignments->first()?->resolution
                ? AssignmentResolution::tryFrom($booking->assignments->first()->resolution)?->color()
                : null,
            'assignment_note' => $booking->assignments->first()?->resolution_note,
            'late_arrival' => $booking->assignments->first()?->late_arrival_flag ?? false,
        ]);

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

        $assignmentResolutions = array_map(
            fn ($case) => [
                'value' => $case->value,
                'label' => $case->label(),
                'color' => $case->color(),
            ],
            AssignmentResolution::cases()
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
            'assignmentResolutions' => $assignmentResolutions,
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
                'status' => $validated['status'],
            ];

            if ($request->hasFile('profile_photo')) {
                $file = $request->file('profile_photo');
                $filename = time().'_'.$file->getClientOriginalName();
                $path = $file->storeAs('profile-photos', $filename, 'public');
                $caregiver->user->update([
                    'profile_photo_path' => $path,
                    'profile_photo_url' => Storage::disk('public')->url($path),
                ]);
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
                            'degree' => $edu['degree'] ?? null,
                        ]);
                    }
                }
            }
        } else {
            $caregiver->update(['status' => $validated['status']]);
        }

        return redirect()->route('caregivers.show', $caregiver->id)
            ->with('success', 'Caregiver updated successfully');
    }

    public function edit(Caregiver $caregiver)
    {
        $caregiver->load(['specialtyTypes', 'locations', 'user', 'certifications', 'attributes', 'educations']);

        $statuses = array_map(fn ($case) => [
            'value' => $case->value,
            'label' => $case->label(),
            'color' => $case->color(),
        ], CaregiverStatus::cases());
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

        if ($path === false) {
            Log::error('Failed to store profile photo for caregiver {id}', ['id' => $caregiver->id]);

            return redirect()->route('caregivers.edit', $caregiver->id)
                ->with('error', 'Failed to upload profile photo. Please try again.');
        }

        $caregiver->user->update([
            'profile_photo_path' => $path,
            'profile_photo_url' => Storage::disk('public')->url($path),
        ]);

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
            'admin_rating' => 'nullable|numeric|min:1|max:5',
            'communication_notes' => 'nullable|string|max:5000',
        ]);

        $updateData = [];
        $internalData = [];

        if (isset($validated['admin_rating'])) {
            $updateData['admin_rating'] = round($validated['admin_rating'], 2);
            $internalData['communication_score'] = round($validated['admin_rating'], 2);
            $internalData['communication_updated_at'] = now();
        }

        if (isset($validated['communication_notes'])) {
            $internalData['communication_notes'] = $validated['communication_notes'];
        }

        if (! empty($updateData)) {
            $caregiver->update($updateData);
        }

        if (! empty($internalData)) {
            $caregiver->internalRating()->updateOrCreate([], $internalData);
        }

        return back()->with('success', 'Admin rating updated successfully');
    }

    public function updateReliabilityOverride(Request $request, Caregiver $caregiver): RedirectResponse
    {
        $validated = $request->validate([
            'reliability_override' => 'nullable|numeric|min:0|max:5',
        ]);

        $rating = $caregiver->internalRating()->firstOrNew([]);

        if (isset($validated['reliability_override'])) {
            $rating->reliability_override = round($validated['reliability_override'], 2);
        } else {
            $rating->reliability_override = null;
        }

        $rating->composite_score = (new RecalculateReliability)->handleSingle($caregiver, $rating);

        $rating->save();

        return back()->with(
            'success',
            isset($validated['reliability_override'])
                ? 'Reliability override saved. Auto-score will be used when cleared.'
                : 'Reliability override cleared. Using auto-calculated score.',
        );
    }

    public function resumeCaregiver(Caregiver $caregiver): RedirectResponse
    {
        $activePause = $caregiver->activePause;

        if (! $activePause) {
            return back()->with('error', 'This caregiver does not have an active pause.');
        }

        $caregiver->update(['status' => CaregiverStatus::Active]);

        $activePause->update(['resumed_at' => now()]);

        return back()->with('success', 'Caregiver has been resumed successfully.');
    }
}
