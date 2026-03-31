<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $query = Client::with(['user']);

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->has('client_type') && $request->client_type) {
            $query->where('client_type', $request->client_type);
        }

        $clients = $query->orderBy('last_name')->paginate(20);

        return Inertia::render('clients/index', [
            'clients' => $clients,
            'filters' => [
                'search' => $request->search,
                'client_type' => $request->client_type,
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('clients/create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'cell_phone' => 'required|string|max:20',
            'client_type' => 'required|in:sd_resident,vacationer,invoiced',
            'password' => 'required|string|min:4|confirmed',
            'how_did_you_hear' => 'nullable|in:concierge,friend_family,google,returning_client,care_com,other',
        ]);

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
            'email' => $validated['email'],
            'cell_phone' => $validated['cell_phone'],
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

        $clients = $query->orderBy('last_name')->limit(6)->get(['id', 'first_name', 'last_name', 'email', 'cell_phone', 'client_type']);

        return response()->json($clients);
    }

    public function show(Client $client)
    {
        $client->load(['user', 'addresses', 'children', 'pets', 'favoriteCaregivers', 'typeChanges.admin']);

        return Inertia::render('clients/show', [
            'client' => [
                'id' => $client->id,
                'first_name' => $client->first_name,
                'last_name' => $client->last_name,
                'email' => $client->email,
                'cell_phone' => $client->cell_phone,
                'client_type' => $client->client_type,
                'how_did_you_hear' => $client->how_did_you_hear,
                'sitter_preferences' => $client->sitter_preferences,
                'other_adults_in_home' => $client->other_adults_in_home,
                'medical_info' => $client->medical_info,
                'emergency_instructions' => $client->emergency_instructions,
                'caregiver_notes' => $client->caregiver_notes,
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
                    'special_needs' => $c->special_needs,
                    'special_needs_notes' => $c->special_needs_notes,
                ]),
                'pets' => $client->pets->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'type' => $p->type,
                    'breed' => $p->breed,
                    'notes' => $p->notes,
                ]),
                'favorite_caregivers' => $client->favoriteCaregivers->map(fn ($c) => [
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
        $client->load(['user', 'addresses', 'children', 'pets']);

        return Inertia::render('clients/edit', [
            'client' => [
                'id' => $client->id,
                'first_name' => $client->first_name,
                'last_name' => $client->last_name,
                'email' => $client->user->email,
                'cell_phone' => $client->cell_phone,
                'client_type' => $client->client_type,
                'how_did_you_hear' => $client->how_did_you_hear,
                'sitter_preferences' => $client->sitter_preferences,
                'other_adults_in_home' => $client->other_adults_in_home,
                'medical_info' => $client->medical_info,
                'emergency_instructions' => $client->emergency_instructions,
                'caregiver_notes' => $client->caregiver_notes,
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
                    'special_needs' => $c->special_needs,
                    'special_needs_notes' => $c->special_needs_notes,
                ]),
                'pets' => $client->pets->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'type' => $p->type,
                    'breed' => $p->breed,
                    'notes' => $p->notes,
                ]),
            ],
            'csrf_token' => csrf_token(),
        ]);
    }

    public function update(Request $request, Client $client)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'cell_phone' => 'required|string|max:20',
            'client_type' => 'required|in:sd_resident,vacationer,invoiced',
            'how_did_you_hear' => 'nullable|in:concierge,friend_family,google,returning_client,care_com,other',
            'sitter_preferences' => 'nullable|array',
            'other_adults_in_home' => 'nullable|string|max:10',
            'medical_info' => 'nullable|string',
            'emergency_instructions' => 'nullable|string',
            'caregiver_notes' => 'nullable|string',
        ]);

        $client->update($validated);

        $client->user->update([
            'name' => $validated['first_name'].' '.$validated['last_name'],
        ]);

        return redirect()->route('clients.show', $client->id)
            ->with('success', 'Client updated successfully');
    }

    public function updateProfilePhoto(Request $request, Client $client)
    {
        $request->validate([
            'profile_photo' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

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

    public function resetPassword(Request $request, Client $client)
    {
        $request->validate([
            'new_password' => 'required|string|min:4|confirmed',
        ]);

        if (! $client->user) {
            return redirect()->route('clients.show', $client->id)
                ->with('error', 'Client does not have a user account');
        }

        $client->user->update([
            'password' => Hash::make($request->new_password),
        ]);

        return redirect()->route('clients.show', $client->id)
            ->with('success', 'Password reset successfully');
    }
}
