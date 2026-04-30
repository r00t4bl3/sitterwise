<?php

namespace App\Http\Controllers;

use App\Enums\ClientType;
use App\Enums\DiscoverySource;
use App\Enums\SitterPreference;
use App\Http\Requests\ResetClientPasswordRequest;
use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientProfilePhotoRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Models\AttributeDefinition;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class ClientController extends Controller
{
    public function __construct()
    {
        $this->clientType = array_map(
            fn ($case) => ['value' => $case->value, 'label' => $case->label()],
            ClientType::cases(),
        );
        $this->sitterPreference = array_map(
            fn ($case) => ['value' => $case->value, 'label' => $case->label()],
            SitterPreference::cases(),
        );
        $this->discoverySources = array_map(
            fn ($case) => ['value' => $case->value, 'label' => $case->label()],
            DiscoverySource::cases(),
        );
    }

    public function index(Request $request)
    {
        $query = Client::with(['user'])->withCount(['children', 'pets']);

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->has('client_type') && $request->client_type && $request->client_type !== 'all') {
            $query->where('client_type', $request->client_type);
        }

        $clients = $query->orderBy('last_name')->paginate(20);

        return Inertia::render('admin/clients/index', [
            'clients' => $clients,
            'filters' => [
                'search' => $request->search,
                'client_type' => $request->client_type ?? 'all',
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('admin/clients/create', [
            'client_types' => $this->clientType,
            'sitter_preferences' => $this->sitterPreference,
            'discovery_sources' => $this->discoverySources,
        ]);
    }

    public function store(StoreClientRequest $request)
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['first_name'].' '.$validated['last_name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'client',
        ]);

        Client::create([
            'user_id' => $user->id,
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'biography' => $validated['biography'] ?? null,
            'phone' => $validated['phone'],
            'client_type' => $validated['client_type'],
            'how_did_you_hear' => $validated['how_did_you_hear'] ?? null,
        ]);

        return redirect()->route('clients.index')
            ->with('success', 'Client created successfully');
    }

    public function searchSuggestions(Request $request)
    {
        $query = Client::with(['user']);

        if ($request->has('q') && $request->q) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $clients = $query->orderBy('last_name')->limit(6)->get(['id', 'first_name', 'last_name', 'phone', 'client_type'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->first_name.' '.$c->last_name,
                'client_type' => $c->client_type,
            ]);

        return response()->json($clients);
    }

    public function getClientData(Client $client)
    {
        $client->load(['addresses', 'children', 'pets', 'favoriteCaregivers.user', 'blockedCaregivers.user']);

        $previousCaregivers = $client->previousCaregivers()->with('user')->get();

        return response()->json([
            'client' => [
                'id' => $client->id,
                'name' => $client->user->name ?? $client->first_name.' '.$client->last_name,
                'client_type' => $client->client_type,
                'addresses' => $client->addresses->map(fn ($a) => [
                    'id' => $a->id,
                    'label' => $a->label,
                    'location_type' => $a->location_type,
                    'line1' => $a->line1,
                    'line2' => $a->line2,
                    'city' => $a->city,
                    'state' => $a->state,
                    'zip' => $a->zip,
                    'is_primary' => $a->is_primary,
                ]),
                'children' => $client->children->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'gender' => $c->gender,
                    'birth_month' => $c->birth_month,
                    'birth_year' => $c->birth_year,
                ]),
                'pets' => $client->pets->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'type' => $p->type,
                    'breed' => $p->breed,
                    'notes' => $p->notes,
                ]),
                'how_did_you_hear' => $client->how_did_you_hear,
                'sitter_preferences' => $client->sitter_preferences,
                'other_adults_present' => $client->other_adults_present,
                'emergency_instructions' => $client->emergency_instructions,
                'favorite_caregivers' => $client->favoriteCaregivers->map(fn ($cg) => [
                    'id' => $cg->id,
                    'first_name' => $cg->first_name,
                    'last_name' => $cg->last_name,
                    'user' => [
                        'profile_photo_path' => $cg->user?->profile_photo_path,
                        'profile_photo_url' => $cg->user?->profile_photo_url,
                    ],
                ]),
                'blocked_caregivers' => $client->blockedCaregivers->map(fn ($cg) => [
                    'id' => $cg->id,
                    'first_name' => $cg->first_name,
                    'last_name' => $cg->last_name,
                    'user' => [
                        'profile_photo_path' => $cg->user?->profile_photo_path,
                        'profile_photo_url' => $cg->user?->profile_photo_url,
                    ],
                ]),
                'previous_caregivers' => $previousCaregivers->map(fn ($cg) => [
                    'id' => $cg->id,
                    'first_name' => $cg->first_name,
                    'last_name' => $cg->last_name,
                    'user' => [
                        'profile_photo_path' => $cg->user?->profile_photo_path,
                        'profile_photo_url' => $cg->user?->profile_photo_url,
                    ],
                ]),
            ],
        ]);
    }

    public function show(Client $client)
    {
        $client->load(['user', 'addresses', 'children', 'pets', 'favoriteCaregivers.user', 'blockedCaregivers.user', 'typeChanges.admin']);

        $previousCaregivers = $client->bookings()
            ->whereNotNull('caregiver_id')
            ->whereIn('status', ['completed', 'confirmed'])
            ->with('caregiver.user')
            ->orderBy('start_datetime', 'desc')
            ->get()
            ->pluck('caregiver')
            ->unique('id')
            ->take(10)
            ->values();
        $client->load(['attributes' => function ($query) {
            $query->withPivot('value');
        }]);

        return Inertia::render('admin/clients/show', [
            'client' => [
                'id' => $client->id,
                'first_name' => $client->first_name,
                'last_name' => $client->last_name,
                'biography' => $client->biography,
                'email' => $client->user->email,
                'phone' => $client->phone,
                'client_type' => $client->client_type,
                'how_did_you_hear' => $client->how_did_you_hear,
                'how_did_you_hear_label' => $client->how_did_you_hear ? DiscoverySource::tryFrom($client->how_did_you_hear)?->label() : null,
                'sitter_preferences' => $client->sitter_preferences,
                'other_adults_present' => $client->other_adults_present,
                'emergency_instructions' => $client->emergency_instructions,
                'special_needs_notes' => $client->special_needs_notes,
                'user' => [
                    'profile_photo_path' => $client->user->profile_photo_path,
                ],
                'addresses' => $client->addresses->map(fn ($a) => [
                    'id' => $a->id,
                    'label' => $a->label,
                    'location_type' => $a->location_type,
                    'line1' => $a->line1,
                    'line2' => $a->line2,
                    'city' => $a->city,
                    'state' => $a->state,
                    'zip' => $a->zip,
                    'is_primary' => $a->is_primary,
                ]),
                'children' => $client->children->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'gender' => $c->gender,
                    'birth_month' => $c->birth_month,
                    'birth_year' => $c->birth_year,
                ]),
                'pets' => $client->pets->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'type' => $p->type,
                    'breed' => $p->breed,
                    'notes' => $p->notes,
                ]),
                'attributes' => $client->attributes->map(fn ($a) => [
                    'id' => $a->id,
                    'attribute_definition' => [
                        'id' => $a->id,
                        'name' => $a->name,
                        'slug' => $a->slug,
                    ],
                    'value' => $a->pivot->value,
                ]),
                'favorite_caregivers' => $client->favoriteCaregivers->map(fn ($c) => [
                    'id' => $c->id,
                    'first_name' => $c->first_name,
                    'last_name' => $c->last_name,
                    'user' => [
                        'profile_photo_path' => $c->user->profile_photo_path,
                    ],
                ]),
                'blocked_caregivers' => $client->blockedCaregivers->map(fn ($c) => [
                    'id' => $c->id,
                    'first_name' => $c->first_name,
                    'last_name' => $c->last_name,
                    'user' => [
                        'profile_photo_path' => $c->user->profile_photo_path,
                    ],
                ]),
                'previous_caregivers' => $previousCaregivers->map(fn ($c) => [
                    'id' => $c->id,
                    'first_name' => $c->first_name,
                    'last_name' => $c->last_name,
                    'user' => [
                        'profile_photo_path' => $c->user->profile_photo_path,
                    ],
                ]),
                'type_changes' => $client->typeChanges->map(fn ($tc) => [
                    'id' => $tc->id,
                    'previous_type' => $tc->previous_type,
                    'new_type' => $tc->new_type,
                    'reason' => $tc->reason,
                    'changed_at' => $tc->changed_at->toISOString(),
                    'admin' => $tc->admin ? [
                        'name' => $tc->admin->name,
                    ] : null,
                ]),
            ],
        ]);
    }

    public function edit(Client $client)
    {
        $client->load(['user', 'addresses', 'children', 'pets', 'attributes', 'favoriteCaregivers.user', 'blockedCaregivers.user']);

        $attributeDefinitions = AttributeDefinition::active()
            ->forClients()
            ->where('type', 'boolean')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'slug' => $a->slug,
                'type' => $a->type,
            ]);

        $caregivers = Caregiver::with('user')->get(['id', 'first_name', 'last_name', 'user_id']);

        return Inertia::render('admin/clients/edit', [
            'client' => [
                'id' => $client->id,
                'first_name' => $client->first_name,
                'last_name' => $client->last_name,
                'biography' => $client->biography,
                'email' => $client->user->email,
                'phone' => $client->phone,
                'client_type' => $client->client_type,
                'how_did_you_hear' => $client->how_did_you_hear,
                'sitter_preferences' => $client->sitter_preferences,
                'other_adults_present' => $client->other_adults_present,
                'emergency_instructions' => $client->emergency_instructions,
                'special_needs_notes' => $client->special_needs_notes,
                'user' => [
                    'profile_photo_path' => $client->user->profile_photo_path,
                ],
                'addresses' => $client->addresses->map(fn ($a) => [
                    'id' => $a->id,
                    'label' => $a->label,
                    'location_type' => $a->location_type,
                    'line1' => $a->line1,
                    'line2' => $a->line2,
                    'city' => $a->city,
                    'state' => $a->state,
                    'zip' => $a->zip,
                    'is_primary' => $a->is_primary,
                ]),
                'children' => $client->children->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'gender' => $c->gender,
                    'birth_month' => $c->birth_month,
                    'birth_year' => $c->birth_year,
                ]),
                'pets' => $client->pets->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'type' => $p->type,
                    'breed' => $p->breed,
                    'notes' => $p->notes,
                ]),
                'attributes' => $client->attributes->map(fn ($a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'slug' => $a->slug,
                    'type' => $a->type,
                    'value' => $a->pivot->value,
                ]),
            ],
            'favorite_caregivers' => $client->favoriteCaregivers->map(fn ($c) => [
                'id' => $c->id,
                'first_name' => $c->first_name,
                'last_name' => $c->last_name,
                'user' => [
                    'profile_photo_path' => $c->user?->profile_photo_path,
                ],
            ]),
            'blocked_caregivers' => $client->blockedCaregivers->map(fn ($c) => [
                'id' => $c->id,
                'first_name' => $c->first_name,
                'last_name' => $c->last_name,
                'user' => [
                    'profile_photo_path' => $c->user?->profile_photo_path,
                ],
            ]),
            'attribute_definitions' => $attributeDefinitions,
            'sitter_preferences' => $this->sitterPreference,
            'client_types' => $this->clientType,
            'discovery_sources' => $this->discoverySources,
            'caregivers' => $caregivers->map(fn ($c) => [
                'id' => $c->id,
                'first_name' => $c->first_name,
                'last_name' => $c->last_name,
                'user' => [
                    'profile_photo_path' => $c->user?->profile_photo_path,
                ],
            ]),
        ]);
    }

    public function update(UpdateClientRequest $request, Client $client)
    {
        $validated = $request->validated();

        $client->update($validated);

        $client->user->update([
            'name' => $validated['first_name'].' '.$validated['last_name'],
        ]);

        if (isset($validated['attributes'])) {
            $attributesToSync = [];
            foreach ($validated['attributes'] as $attributeId => $value) {
                $attributesToSync[$attributeId] = [
                    'value' => $value,
                    'entity_type' => 'client',
                ];
            }
            $client->attributes()->sync($attributesToSync);
        }

        if (isset($validated['children'])) {
            $existingChildIds = $client->children()->pluck('id')->toArray();
            $submittedChildIds = [];

            foreach ($validated['children'] as $childData) {
                if (isset($childData['id']) && in_array($childData['id'], $existingChildIds)) {
                    $child = $client->children()->find($childData['id']);
                    if ($child) {
                        $child->update([
                            'name' => $childData['name'] ?? null,
                            'gender' => $childData['gender'] ?? null,
                            'birth_month' => $childData['birth_month'] ?? null,
                            'birth_year' => $childData['birth_year'] ?? null,
                        ]);
                        $submittedChildIds[] = $childData['id'];
                    }
                } else {
                    $newChild = $client->children()->create([
                        'name' => $childData['name'] ?? null,
                        'gender' => $childData['gender'] ?? null,
                        'birth_month' => $childData['birth_month'] ?? null,
                        'birth_year' => $childData['birth_year'] ?? null,
                    ]);
                    $submittedChildIds[] = $newChild->id;
                }
            }

            $client->children()->whereNotIn('id', $submittedChildIds)->delete();
        }

        if (isset($validated['pets'])) {
            $existingPetIds = $client->pets()->pluck('id')->toArray();
            $submittedPetIds = [];

            foreach ($validated['pets'] as $petData) {
                if (isset($petData['id']) && in_array($petData['id'], $existingPetIds)) {
                    $pet = $client->pets()->find($petData['id']);
                    if ($pet) {
                        $pet->update([
                            'name' => $petData['name'] ?? null,
                            'type' => $petData['type'] ?? null,
                            'breed' => $petData['breed'] ?? null,
                            'notes' => $petData['notes'] ?? null,
                        ]);
                        $submittedPetIds[] = $petData['id'];
                    }
                } else {
                    $newPet = $client->pets()->create([
                        'name' => $petData['name'] ?? null,
                        'type' => $petData['type'] ?? null,
                        'breed' => $petData['breed'] ?? null,
                        'notes' => $petData['notes'] ?? null,
                    ]);
                    $submittedPetIds[] = $newPet->id;
                }
            }

            $client->pets()->whereNotIn('id', $submittedPetIds)->delete();
        }

        if (isset($validated['addresses'])) {
            $existingAddressIds = $client->addresses()->pluck('id')->toArray();
            $submittedAddressIds = [];

            foreach ($validated['addresses'] as $addressData) {
                if (isset($addressData['id']) && in_array($addressData['id'], $existingAddressIds)) {
                    $address = $client->addresses()->find($addressData['id']);
                    if ($address) {
                        $address->update([
                            'label' => $addressData['label'] ?? null,
                            'location_type' => $addressData['location_type'] ?? 'private_home',
                            'line1' => $addressData['line1'] ?? '',
                            'line2' => $addressData['line2'] ?? null,
                            'city' => $addressData['city'] ?? '',
                            'state' => $addressData['state'] ?? '',
                            'zip' => $addressData['zip'] ?? '',
                            'is_primary' => $addressData['is_primary'] ?? false,
                        ]);
                        $submittedAddressIds[] = $addressData['id'];
                    }
                } else {
                    $newAddress = $client->addresses()->create([
                        'label' => $addressData['label'] ?? null,
                        'location_type' => $addressData['location_type'] ?? 'private_home',
                        'line1' => $addressData['line1'] ?? '',
                        'line2' => $addressData['line2'] ?? null,
                        'city' => $addressData['city'] ?? '',
                        'state' => $addressData['state'] ?? '',
                        'zip' => $addressData['zip'] ?? '',
                        'is_primary' => $addressData['is_primary'] ?? false,
                    ]);
                    $submittedAddressIds[] = $newAddress->id;
                }
            }

            $client->addresses()->whereNotIn('id', $submittedAddressIds)->delete();
        }

        if ($request->filled('favorite_caregiver_ids')) {
            $client->favoriteCaregivers()->sync($request->input('favorite_caregiver_ids', []));
        }
        if ($request->filled('blocked_caregiver_ids')) {
            $client->blockedCaregivers()->sync($request->input('blocked_caregiver_ids', []));
        }

        return redirect()->route('clients.show', $client->id)
            ->with('success', 'Client updated successfully');
    }

    public function updateProfilePhoto(UpdateClientProfilePhotoRequest $request, Client $client)
    {
        if ($request->hasFile('profile_photo')) {
            $file = $request->file('profile_photo');
            $filename = time().'_'.$file->getClientOriginalName();
            $path = $file->storeAs('profile-photos', $filename, 'public');
            $client->user->update(['profile_photo_path' => $path]);

            return redirect()->route('clients.edit', $client->id)
                ->with('success', 'Profile photo updated successfully');
        }

        return response()->json(['success' => false], 422);
    }

    public function resetPassword(ResetClientPasswordRequest $request, Client $client)
    {
        $validated = $request->validated();

        if (! $client->user) {
            return redirect()->route('clients.show', $client->id)
                ->with('error', 'Client does not have a user account');
        }

        $client->user->update([
            'password' => Hash::make($validated['new_password']),
        ]);

        return redirect()->route('clients.show', $client->id)
            ->with('success', 'Password reset successfully');
    }
}
