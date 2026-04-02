<?php

namespace App\Services\Availability\Contracts;

use Illuminate\Http\Request;

interface AvailabilityServiceInterface
{
    public function index();

    public function show($id);

    public function update(Request $request, $id);

    public function destroy($id);
}
