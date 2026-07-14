<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrustlineReimbursement extends Model
{
    protected $fillable = [
        'caregiver_id',
        'jobs_completed',
        'reward_amount',
        'notified_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'jobs_completed' => 'integer',
            'reward_amount' => 'integer',
            'notified_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(Caregiver::class);
    }
}
