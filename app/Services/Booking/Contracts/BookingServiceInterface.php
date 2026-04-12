<?php
namespace App\Services\Booking\Contracts;

use Illuminate\Http\Request;

interface BookingServiceInterface
{
    public function index(Request $request);

    // public function store(Request $request);

    // public function update(Request $request, $id);

    // public function destroy($id);

    // public function searchHotels(Request $request);

    // public function notify(Request $request, $id);

    // public function recommendedCaregivers(Request $request);
}