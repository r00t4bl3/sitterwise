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

    public function storeWeek(Request $request);

    public function getMonth(int $year, int $month);
}
