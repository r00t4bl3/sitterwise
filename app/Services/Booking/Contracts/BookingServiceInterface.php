<?php

namespace App\Services\Booking\Contracts;

use App\Models\Booking;
use Illuminate\Http\Request;

interface BookingServiceInterface
{
    public function index(Request $request);

    // public function create(Request $request);

    public function store(Request $request);

    public function show(Request $request, Booking $booking);

    public function update(Request $request, Booking $booking);

    public function destroy(Booking $booking);

    public function processPayment(Request $request, Booking $booking);
}
