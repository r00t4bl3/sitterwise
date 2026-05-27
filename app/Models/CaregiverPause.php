<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaregiverPause extends Model
{
    protected $fillable = [
        'caregiver_id',
        'paused_at',
        'resume_by',
        'pause_reason',
        'resumed_at',
    ];

    protected $casts = [
        'paused_at' => 'datetime',
        'resume_by' => 'date',
        'resumed_at' => 'datetime',
    ];

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(Caregiver::class);
    }

    public function scopeActive($query)
    {
        return $query->whereNull('resumed_at');
    }
}
