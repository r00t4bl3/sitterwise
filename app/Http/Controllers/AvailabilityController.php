<?php

namespace App\Http\Controllers;

use App\Models\Availability;
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

    public function store(Request $request, string $caregiverId)
    {
        return $this->service->store($request, $caregiverId);
    }

    public function show(Request $request, string $id)
    {
        if ($request->has(['year', 'month'])) {
            return $this->service->getMonth(
                (int) $request->query('year'),
                (int) $request->query('month'),
                $id,
            );
        }

        return $this->service->show($id);
    }

    public function update(Request $request, $id)
    {
        return $this->service->update($request, $id);
    }

    public function destroy(Availability $availability)
    {
        return $this->service->destroy($availability);
    }
}
