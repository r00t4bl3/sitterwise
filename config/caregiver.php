<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Caregiver Buffer Minutes
    |--------------------------------------------------------------------------
    |
    | The minimum gap (in minutes) enforced between a caregiver's existing
    | bookings and a new booking on the same day. This gives caregivers
    | time to travel between appointments.
    |
    */
    'buffer_minutes' => env('CAREGIVER_BUFFER_MINUTES', 60),
];
