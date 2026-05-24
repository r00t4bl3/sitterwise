<?php

namespace App\Models;

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
            ->where('last_activity_at', '<', now()->subHours(48))
            ->where(function ($q) {
                $q->whereNull('nudged_at')
                    ->orWhere('nudged_at', '<', now()->subDay());
            });
    }

    public function scopeStale($query)
    {
        return $query->whereNull('archived_at')
            ->where('last_activity_at', '<', now()->subDays(14));
    }

    public function scopeExpired($query)
    {
        return $query->where('last_activity_at', '<', now()->subDays(90));
    }
}
