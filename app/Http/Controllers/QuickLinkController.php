<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuickLinkRequest;
use App\Http\Requests\UpdateQuickLinkRequest;
use App\Models\QuickLink;
use Illuminate\Http\Request;
use Inertia\Inertia;

class QuickLinkController extends Controller
{
    public function index()
    {
        $quickLinks = QuickLink::all();

        return Inertia::render('superadmin/quick-links/index', [
            'quickLinks' => $quickLinks,
        ]);
    }

    public function store(StoreQuickLinkRequest $request)
    {
        $validated = $request->validated();

        QuickLink::create($validated);

        return redirect()->route('quick-links.index')
            ->with('success', 'Quick Link created successfully');
    }

    public function update(UpdateQuickLinkRequest $request, QuickLink $quickLink)
    {
        $validated = $request->validated();

        $quickLink->update($validated);

        return redirect()->route('quick-links.index')
            ->with('success', 'Quick Link updated successfully');
    }

    public function destroy(QuickLink $quickLink)
    {
        $quickLink->delete();

        return redirect()->route('quick-links.index')
            ->with('success', 'Quick Link deleted successfully');
    }

    public function search(Request $request)
    {
        $query = $request->input('q', '');

        $quickLinks = QuickLink::where('title', 'like', "%{$query}%")
            ->orWhere('description', 'like', "%{$query}%")
            ->limit(10)
            ->get(['id', 'title'])
            ->map(fn ($link) => [
                'id' => $link->id,
                'name' => $link->title,
            ]);

        return response()->json($quickLinks);
    }
}
