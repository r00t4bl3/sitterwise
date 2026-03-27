<?php

namespace App\Policies;

use App\Models\Availability;
use App\Models\User;

class AvailabilityPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'caregiver', 'client']);
    }

    public function view(User $user, Availability $availability): bool
    {
        return $user->isAdmin()
            || $user->caregiver?->id === $availability->caregiver_id;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'caregiver']);
    }

    public function update(User $user, Availability $availability): bool
    {
        return $user->isAdmin()
            || $user->caregiver?->id === $availability->caregiver_id;
    }

    public function delete(User $user, Availability $availability): bool
    {
        return $user->isAdmin()
            || $user->caregiver?->id === $availability->caregiver_id;
    }
}
