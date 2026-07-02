<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Helpers for computing day/month/year boundaries in the business timezone.
 *
 * Datetimes are stored in UTC. To query them against a "today"/"this month"
 * window as a human in Pacific would expect, compute the boundary in the
 * business timezone and convert it back to UTC (`->utc()`) before it hits the
 * query — otherwise the boundary lands on UTC midnight and is off by up to a
 * day (e.g. after ~5pm Pacific, `now()->startOfDay()` is already tomorrow).
 */
class BusinessTime
{
    public const TZ = 'America/Los_Angeles';

    /**
     * "Now" in the business timezone. Chain `->startOfDay()->utc()` (etc.) and
     * pass the result to a query against a UTC column.
     */
    public static function now(): Carbon
    {
        return Carbon::now(self::TZ);
    }
}
