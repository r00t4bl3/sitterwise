<?php

namespace App\Services\Availability\Contracts;

use App\Models\Availability;
use Illuminate\Http\Request;

interface AvailabilityServiceInterface
{
    public function index();

    public function show($id);

    public function update(Request $request, $id);

    public function destroy(Availability $availability);

    public function store(Request $request, string $caregiverId);

    public function getMonth(int $year, int $month, string $caregiverId);
}
