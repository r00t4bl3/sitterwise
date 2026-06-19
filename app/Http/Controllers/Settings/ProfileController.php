<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        if ($user->isCaregiver() && $user->caregiver) {
            $firstName = $user->caregiver->first_name;
            $lastName = $user->caregiver->last_name;
        } elseif ($user->isClient() && $user->client) {
            $firstName = $user->client->first_name;
            $lastName = $user->client->last_name;
        } else {
            $parts = explode(' ', $user->name, 2);
            $firstName = $parts[0];
            $lastName = $parts[1] ?? '';
        }

        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'firstName' => $firstName,
            'lastName' => $lastName,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        if ($user->isCaregiver() && $user->caregiver) {
            $user->caregiver->update([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
            ]);
        } elseif ($user->isClient() && $user->client) {
            $user->client->update([
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
            ]);
        } else {
            $user->name = $validated['first_name'].' '.$validated['last_name'];
        }

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
