<?php

namespace App\Http\Controllers;

use App\Models\InterviewTalkingPoint;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InterviewTalkingPointController extends Controller
{
    public function index()
    {
        $talkingPoints = InterviewTalkingPoint::ordered()->get();

        return Inertia::render('superadmin/talking-points/Index', [
            'talkingPoints' => $talkingPoints,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'label' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
        ]);

        $maxSort = InterviewTalkingPoint::max('sort_order') ?? 0;

        InterviewTalkingPoint::create([
            'label' => $validated['label'],
            'description' => $validated['description'] ?? null,
            'sort_order' => $maxSort + 1,
        ]);

        return back()->with('success', 'Talking point added.');
    }

    public function update(Request $request, InterviewTalkingPoint $talkingPoint)
    {
        $validated = $request->validate([
            'label' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
        ]);

        $talkingPoint->update($validated);

        return back()->with('success', 'Talking point updated.');
    }

    public function destroy(InterviewTalkingPoint $talkingPoint)
    {
        $talkingPoint->delete();

        return back()->with('success', 'Talking point deleted.');
    }

    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|integer|exists:interview_talking_points,id',
        ]);

        foreach ($validated['ids'] as $index => $id) {
            InterviewTalkingPoint::where('id', $id)->update(['sort_order' => $index]);
        }

        return back()->with('success', 'Talking points reordered.');
    }
}
