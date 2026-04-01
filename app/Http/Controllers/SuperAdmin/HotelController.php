<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HotelController extends Controller
{
    public function index()
    {
        $hotels = Hotel::orderBy('name')->get();

        return Inertia::render('admin/hotels/index', [
            'hotels' => $hotels,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'line1' => 'required|string',
            'line2' => 'nullable|string',
            'city' => 'required|string',
            'state' => 'required|string',
            'zip' => 'required|string',
            'parking_instructions' => 'required|string',
            'hourly_rate' => 'required|numeric|min:0',
            'resort_fee' => 'nullable|numeric|min:0',
            'contact_name' => 'nullable|string',
            'contact_phone' => 'nullable|string',
            'admin_notes' => 'nullable|string',
        ]);

        Hotel::create($validated);

        return redirect()->route('admin.hotels.index')
            ->with('success', 'Hotel created successfully');
    }

    public function update(Request $request, Hotel $hotel)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'line1' => 'required|string',
            'line2' => 'nullable|string',
            'city' => 'required|string',
            'state' => 'required|string',
            'zip' => 'required|string',
            'parking_instructions' => 'required|string',
            'hourly_rate' => 'required|numeric|min:0',
            'resort_fee' => 'nullable|numeric|min:0',
            'contact_name' => 'nullable|string',
            'contact_phone' => 'nullable|string',
            'admin_notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $hotel->update($validated);

        return redirect()->route('admin.hotels.index')
            ->with('success', 'Hotel updated successfully');
    }

    public function destroy(Hotel $hotel)
    {
        $hotel->delete();

        return redirect()->route('admin.hotels.index')
            ->with('success', 'Hotel deleted successfully');
    }
}
