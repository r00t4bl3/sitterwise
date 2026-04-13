<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHotelRequest;
use App\Http\Requests\UpdateHotelRequest;
use App\Models\Hotel;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HotelController extends Controller
{
    public function index()
    {
        $hotels = Hotel::orderBy('name')->get();

        return Inertia::render('superadmin/hotels/index', [
            'hotels' => $hotels,
        ]);
    }

    public function store(StoreHotelRequest $request)
    {
        $validated = $request->validated();

        Hotel::create($validated);

        return redirect()->route('hotels.index')
            ->with('success', 'Hotel created successfully');
    }

    public function update(UpdateHotelRequest $request, Hotel $hotel)
    {
        $validated = $request->validated();

        $hotel->update($validated);

        return redirect()->route('hotels.index')
            ->with('success', 'Hotel updated successfully');
    }

    public function destroy(Hotel $hotel)
    {
        $hotel->delete();

        return redirect()->route('hotels.index')
            ->with('success', 'Hotel deleted successfully');
    }

    public function search(Request $request)
    {
        $query = $request->input('q', '');

        $hotels = Hotel::where('is_active', true)
            ->where('name', 'like', "%{$query}%")
            ->limit(10)
            ->get(['id', 'name', 'city'])
            ->map(fn ($h) => [
                'id' => $h->id,
                'name' => $h->name.($h->city ? ", {$h->city}" : ''),
            ]);

        return response()->json($hotels);
    }
}
