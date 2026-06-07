<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CaregiverInterview extends Model
{
    protected $fillable = [
        'caregiver_id',
        'evaluator_id',
        'application_id',
        'scores',
        'composite',
        'notes',
        'status',
        'evaluated_at',
    ];

    protected function casts(): array
    {
        return [
            'scores' => 'array',
            'composite' => 'integer',
            'evaluated_at' => 'datetime',
        ];
    }

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(Caregiver::class);
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(CaregiverApplication::class);
    }

    public function talkingPoints(): HasMany
    {
        return $this->hasMany(CaregiverInterviewTalkingPoint::class, 'caregiver_interview_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
