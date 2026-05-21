<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferenceRequest extends Model
{
    protected $fillable = [
        'token',
        'caregiver_id',
        'reference_name',
        'reference_email',
        'relationship',
        'years_known',
        'is_sponsor',
        'rating',
        'feedback',
        'submitted_at',
    ];

    protected $casts = [
        'is_sponsor' => 'boolean',
        'submitted_at' => 'datetime',
    ];

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(Caregiver::class);
    }

    public function scopePending($query)
    {
        return $query->whereNull('submitted_at');
    }

    public function scopeCompleted($query)
    {
        return $query->whereNotNull('submitted_at');
    }
}
