<?php

namespace App\Models;

use App\Support\Settings;
use Illuminate\Database\Eloquent\Model;

class IncompleteApplication extends Model
{
    protected $fillable = [
        'email',
        'last_step',
        'draft_data',
        'nudged_at',
        'nudge_count',
        'archived_at',
        'last_activity_at',
    ];

    protected $casts = [
        'draft_data' => 'array',
        'nudged_at' => 'datetime',
        'archived_at' => 'datetime',
        'last_activity_at' => 'datetime',
    ];

    public function scopeNeedsNudge($query)
    {
        return $query->whereNull('archived_at')
            ->where('last_activity_at', '<', now()->subHours((int) Settings::get('applications.needs_nudge_hours', 48)))
            ->where(function ($q) {
                $q->whereNull('nudged_at')
                    ->orWhere('nudged_at', '<', now()->subDay());
            });
    }

    public function scopeStale($query)
    {
        return $query->whereNull('archived_at')
            ->where('last_activity_at', '<', now()->subDays((int) Settings::get('applications.stale_days', 14)));
    }

    public function scopeExpired($query)
    {
        return $query->where('last_activity_at', '<', now()->subDays((int) Settings::get('applications.expired_days', 90)));
    }
}
