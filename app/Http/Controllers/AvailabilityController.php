<?php

namespace App\Http\Controllers;

use App\Services\Availability\AvailabilityServiceFactory;
use Illuminate\Http\Request;

class AvailabilityController extends Controller
{
    protected $service;

    public function __construct(AvailabilityServiceFactory $factory)
    {
        $this->service = $factory->make();
    }

    public function index(Request $request)
    {
        return $this->service->index();
    }

    public function show($id)
    {
        return $this->service->show($id);
    }

    public function update(Request $request, $id)
    {
        return $this->service->update($request, $id);
    }

    public function destroy($id)
    {
        return $this->service->destroy($id);
    }
}
