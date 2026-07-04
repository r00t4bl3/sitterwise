<?php

namespace App\Http\Controllers;

use App\Http\Requests\CancelBookingRequest;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Requests\UpdateBookingRequest;
use App\Models\Booking;
use App\Models\BookingGroup;
use App\Services\Booking\AdminBookingService;
use App\Services\Booking\BookingServiceFactory;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    protected $service;

    public function __construct(BookingServiceFactory $factory)
    {
        $this->service = $factory->make();
    }

    public function index(Request $request)
    {
        return $this->service->index($request);
    }

    public function create(Request $request)
    {
        return $this->service->create($request);
    }

    public function show(Request $request, Booking $booking)
    {
        return $this->service->show($request, $booking);
    }

    public function store(StoreBookingRequest $request)
    {
        return $this->service->store($request);
    }

    public function update(UpdateBookingRequest $request, Booking $booking)
    {
        return $this->service->update($request, $booking);
    }

    public function cancel(CancelBookingRequest $request, Booking $booking)
    {
        return $this->service->cancel($request, $booking);
    }

    public function replaceCaregiver(Request $request, Booking $booking)
    {
        return $this->service->replaceCaregiver($request, $booking);
    }

    public function reopen(Request $request, Booking $booking)
    {
        return $this->service->reopen($request, $booking);
    }

    public function destroy(Booking $booking)
    {
        return $this->service->destroy($booking);
    }

    public function notify(Request $request, Booking $booking)
    {
        return $this->service->notify($request, $booking);
    }

    public function recommendedCaregivers(Request $request)
    {
        return $this->service->recommendedCaregivers($request);
    }

    public function reserve(Request $request, Booking $booking)
    {
        return $this->service->reserve($request, $booking);
    }

    public function confirm(Request $request, Booking $booking)
    {
        return $this->service->confirm($request, $booking);
    }

    public function release(Request $request, Booking $booking)
    {
        return $this->service->release($request, $booking);
    }

    public function processPayment(Request $request, Booking $booking)
    {
        return $this->service->processPayment($request, $booking);
    }

    public function splitGroup(Request $request, BookingGroup $bookingGroup)
    {
        abort_unless($request->user()->isAdmin(), 403);

        return app(AdminBookingService::class)->splitGroup($request, $bookingGroup);
    }

    public function export(Request $request)
    {
        return $this->service->export($request);
    }
}
