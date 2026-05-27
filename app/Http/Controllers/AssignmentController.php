<?php

namespace App\Http\Controllers;

use App\Enums\AssignmentResolution;
use App\Mail\AdminCaregiverBackedOutMail;
use App\Models\CaregiverAssignment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

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

        $assignment->resolve(AssignmentResolution::BackedOut, $validated['reason']);

        $adminEmails = User::whereIn('role', ['admin', 'super_admin'])->pluck('email');
        $caregiverName = $caregiver->first_name.' '.$caregiver->last_name;
        foreach ($adminEmails as $adminEmail) {
            Mail::to($adminEmail)->queue(new AdminCaregiverBackedOutMail(
                caregiverName: $caregiverName,
                caregiverId: $caregiver->id,
                bookingId: $assignment->booking_id,
                reason: $validated['reason'],
            ));
        }

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

        return back()->with('success', 'Back-out excused.');
    }

    public function logNoShow(Request $request, CaregiverAssignment $assignment): RedirectResponse
    {
        $validated = $request->validate([
            'note' => 'nullable|string|max:1000',
        ]);

        $assignment->resolve(AssignmentResolution::NoShow, $validated['note'] ?? null);

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
