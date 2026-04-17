<?php
namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Http\Request;
use Inertia\Inertia;

class JobController extends Controller
{
    public function index(Request $request)
    {
        $caregiver = $request->user()->caregiver;

        if (! $caregiver) {
            abort(403, 'Caregiver profile not found');
        }

        $bookings = Booking::with(['client.user', 'hotel', 'address'])
            ->where('caregiver_id', $caregiver->id)
            ->orderBy('start_datetime', 'desc')
            ->paginate(15);

        return Inertia::render('caregiver/jobs/index', [
            'jobs' => $bookings,
        ]);
    }

    public function checkout(Request $request, Booking $booking)
    {
        $caregiver = $request->user()->caregiver;

        if (! $caregiver) {
            abort(403, 'Caregiver profile not found');
        }

        if ($booking->caregiver_id !== $caregiver->id) {
            abort(403, 'You are not authorized to checkout this job');
        }

        $validated = $request->validate([
            'start_datetime'            => 'required|date',
            'end_datetime'              => 'required|date|after:start_datetime',
            'reimbursement'             => 'nullable|numeric|min:0',
            'reimbursement_description' => 'nullable|string|max:255',
            'bonus'                     => 'nullable|numeric|min:0',
        ]);

        $booking->update([
            'start_datetime'            => $validated['start_datetime'],
            'end_datetime'              => $validated['end_datetime'],
            'reimbursement'             => $validated['reimbursement'] ?? null,
            'reimbursement_description' => $validated['reimbursement_description'] ?? null,
            'bonus'                     => $validated['bonus'] ?? null,
            'status'                    => BookingStatus::Completed->value,
        ]);

        return back()->with('success', 'Job updated successfully');
    }
}