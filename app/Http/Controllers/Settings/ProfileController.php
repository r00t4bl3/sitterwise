<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Http\Requests\Settings\UpdateProfilePhotoRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
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
     * Update the authenticated user's profile photo (admin/superadmin only for now).
     */
    public function updatePhoto(UpdateProfilePhotoRequest $request): RedirectResponse
    {
        $user = $request->user();
        $file = $request->file('profile_photo');

        $path = $file->storeAs(
            'profile-photos',
            time().'_'.$file->getClientOriginalName(),
            'public',
        );

        if ($path === false) {
            return redirect()->route('profile.edit')->with('error', 'Failed to upload photo. Please try again.');
        }

        // Remove the previous uploaded photo (skip the shared default asset).
        if ($user->profile_photo_path && $user->profile_photo_path !== 'avatar.jpg') {
            Storage::disk('public')->delete($user->profile_photo_path);
        }

        $user->update([
            'profile_photo_path' => $path,
            'profile_photo_url' => Storage::disk('public')->url($path),
        ]);

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
