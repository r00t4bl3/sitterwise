<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookingRequest;
use App\Http\Requests\UpdateBookingRequest;
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

    public function show(Request $request, $id)
    {
        return $this->service->show($request, $id);
    }

    public function store(StoreBookingRequest $request)
    {
        return $this->service->store($request);
    }

    public function update(UpdateBookingRequest $request, $id)
    {
        return $this->service->update($request, $id);
    }

    public function destroy($id)
    {
        return $this->service->destroy($id);
    }

    public function notify(Request $request, $id)
    {
        return $this->service->notify($request, $id);
    }

    public function recommendedCaregivers(Request $request)
    {
        return $this->service->recommendedCaregivers($request);
    }

    public function reserve(Request $request, $id)
    {
        return $this->service->reserve($request, $id);
    }

    public function confirm(Request $request, $id)
    {
        return $this->service->confirm($request, $id);
    }

    public function release(Request $request, $id)
    {
        return $this->service->release($request, $id);
    }
}
