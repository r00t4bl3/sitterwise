<?php

namespace App\Http\Controllers\Settings;

use App\Enums\CaregiverStatus;
use App\Http\Controllers\Controller;
use App\Models\CaregiverPause;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CaregiverPauseController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user->isCaregiver()) {
            return redirect()->route('profile.edit');
        }

        $caregiver = $user->caregiver;
        $activePause = CaregiverPause::active()->where('caregiver_id', $caregiver->id)->first();

        return Inertia::render('settings/pause', [
            'caregiver' => [
                'status' => [
                    'value' => $caregiver->status->value,
                    'label' => $caregiver->status->label(),
                ],
            ],
            'activePause' => $activePause ? [
                'paused_at' => $activePause->paused_at->toIso8601String(),
                'resume_by' => $activePause->resume_by?->format('Y-m-d'),
                'pause_reason' => $activePause->pause_reason,
            ] : null,
        ]);
    }

    public function pause(Request $request): RedirectResponse
    {
        $caregiver = $request->user()->caregiver;

        if ($caregiver->status !== CaregiverStatus::Active) {
            return back()->with('error', 'Only active caregivers can pause their account.');
        }

        $validated = $request->validate([
            'resume_by' => 'nullable|date|after:today',
            'pause_reason' => 'nullable|string|max:1000',
        ]);

        CaregiverPause::create([
            'caregiver_id' => $caregiver->id,
            'paused_at' => now(),
            'resume_by' => $validated['resume_by'] ?? null,
            'pause_reason' => $validated['pause_reason'] ?? null,
        ]);

        $caregiver->update(['status' => CaregiverStatus::OnHold]);

        return redirect()->route('settings.caregiver.pause')
            ->with('success', 'Your account has been paused.');
    }

    public function resume(Request $request): RedirectResponse
    {
        $caregiver = $request->user()->caregiver;

        if ($caregiver->status !== CaregiverStatus::OnHold) {
            return back()->with('error', 'Your account is not currently paused.');
        }

        $activePause = CaregiverPause::active()->where('caregiver_id', $caregiver->id)->first();

        if ($activePause) {
            $activePause->update(['resumed_at' => now()]);
        }

        $caregiver->update(['status' => CaregiverStatus::Active]);

        return redirect()->route('settings.caregiver.pause')
            ->with('success', 'Your account has been resumed.');
    }
}
