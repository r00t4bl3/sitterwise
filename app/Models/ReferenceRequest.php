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
        'rating_reliability',
        'rating_trustworthiness',
        'rating_maturity',
        'rating_communication',
        'rating_warmth',
        'rating_overall_recommendation',
        'strengths',
        'concerns',
        'additional_comments',
        'submitted_at',
    ];

    protected $casts = [
        'is_sponsor' => 'boolean',
        'submitted_at' => 'datetime',
        'rating_reliability' => 'integer',
        'rating_trustworthiness' => 'integer',
        'rating_maturity' => 'integer',
        'rating_communication' => 'integer',
        'rating_warmth' => 'integer',
        'rating_overall_recommendation' => 'integer',
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
