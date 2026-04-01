<?php
namespace App\Services\Availability;

use App\Services\Availability\Contracts\AvailabilityServiceInterface;

class AvailabilityServiceFactory
{
    public function make(): AvailabilityServiceInterface
    {
        return match (auth()->user()->role) {
            'admin', 'super_admin' => app(AdminAvailabilityService::class),
            'caregiver' => app(CaregiverAvailabilityService::class),
            default     => abort(403, 'Unauthorized role'),
        };
    }
}