<?php

namespace App\Http\Controllers;

use App\Enums\AssignmentResolution;
use App\Models\CaregiverAssignment;
use App\Models\User;
use App\Notifications\AdminCaregiverBackedOutNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class AssignmentController extends Controller
{
    public function backOut(Request $request, CaregiverAssignment $assignment): RedirectResponse
    {
        $caregiver = $request->user()->caregiver;

        if ($assignment->caregiver_id !== $caregiver->id) {
            abort(403, 'This assignment does not belong to you.');
        }

        if ($assignment->resolution !== null) {
            return back()->with('error', 'This assignment has already been resolved.');
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        DB::transaction(function () use ($assignment, $validated) {
            $assignment->resolve(AssignmentResolution::BackedOut, $validated['reason']);

            $assignment->booking->update(['caregiver_id' => null]);
        });

        $admins = User::where('role', 'admin')->get();
        $caregiverName = $caregiver->first_name.' '.$caregiver->last_name;
        Notification::send($admins, new AdminCaregiverBackedOutNotification(
            caregiverName: $caregiverName,
            caregiverId: $caregiver->id,
            bookingId: $assignment->booking_id,
            reason: $validated['reason'],
        ));

        Artisan::queue('app:recalculate-reliability', ['--caregiver' => $caregiver->id]);

        return back()->with('success', 'You have backed out of this job. A notification has been sent to the team.');
    }

    public function excuse(Request $request, CaregiverAssignment $assignment): RedirectResponse
    {
        $validated = $request->validate([
            'note' => 'required|string|max:1000',
        ]);

        $assignment->update([
            'resolution' => AssignmentResolution::BackedOutExcused->value,
            'resolution_at' => now(),
            'resolution_note' => $validated['note'],
            'excused_by' => $request->user()->id,
            'excused_at' => now(),
        ]);

        Artisan::queue('app:recalculate-reliability', ['--caregiver' => $assignment->caregiver_id]);

        return back()->with('success', 'Back-out excused.');
    }

    public function logNoShow(Request $request, CaregiverAssignment $assignment): RedirectResponse
    {
        $validated = $request->validate([
            'note' => 'nullable|string|max:1000',
        ]);

        $assignment->resolve(AssignmentResolution::NoShow, $validated['note'] ?? null);

        Artisan::queue('app:recalculate-reliability', ['--caregiver' => $assignment->caregiver_id]);

        return back()->with('success', 'No-show logged.');
    }

    public function logLateArrival(Request $request, CaregiverAssignment $assignment): RedirectResponse
    {
        $validated = $request->validate([
            'note' => 'nullable|string|max:1000',
        ]);

        $assignment->update([
            'late_arrival_flag' => true,
            'late_arrival_note' => $validated['note'] ?? null,
        ]);

        return back()->with('success', 'Late arrival logged.');
    }
}
