<?php

namespace App\Http\Controllers\Settings;

use App\Enums\ForeignLanguage;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CaregiverLanguagesController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user->isCaregiver()) {
            return redirect()->route('profile.edit');
        }

        $caregiver = $user->caregiver;

        return Inertia::render('settings/languages', [
            'languageOptions' => ForeignLanguage::toArray(),
            'selectedLanguages' => $caregiver->languages ?? [],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->isCaregiver()) {
            return redirect()->route('profile.edit');
        }

        $validated = $request->validate([
            'languages' => 'nullable|array',
            'languages.*' => ['string', Rule::enum(ForeignLanguage::class)],
        ]);

        $user->caregiver->update(['languages' => $validated['languages'] ?? []]);

        return redirect()->route('settings.caregiver.languages')
            ->with('success', 'Your languages have been updated.');
    }
}
