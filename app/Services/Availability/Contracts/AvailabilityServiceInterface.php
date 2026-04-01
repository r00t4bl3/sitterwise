<?php

namespace App\Services\Availability\Contracts;

use Illuminate\Http\Request;

interface AvailabilityServiceInterface
{
    public function index();

    public function store(Request $request);

    public function update(Request $request, $id);

    public function destroy($id);
}
