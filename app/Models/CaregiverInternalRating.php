<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaregiverInternalRating extends Model
{
    protected $fillable = [
        'caregiver_id',
        'communication_score',
        'communication_notes',
        'communication_updated_at',
        'reliability_score',
        'reliability_override',
        'reliability_cached_at',
        'composite_score',
    ];

    protected function casts(): array
    {
        return [
            'communication_score' => 'decimal:2',
            'reliability_score' => 'decimal:2',
            'reliability_override' => 'decimal:2',
            'composite_score' => 'decimal:2',
            'communication_updated_at' => 'datetime',
            'reliability_cached_at' => 'datetime',
        ];
    }

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(Caregiver::class);
    }

    public function effectiveReliability(): ?float
    {
        if ($this->reliability_override !== null) {
            return (float) $this->reliability_override;
        }

        return $this->reliability_score !== null ? (float) $this->reliability_score : null;
    }
}
