<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaregiverInterviewTalkingPoint extends Model
{
    protected $fillable = [
        'caregiver_interview_id',
        'talking_point_id',
        'label',
        'sort_order',
        'is_checked',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_checked' => 'boolean',
        ];
    }

    public function interview(): BelongsTo
    {
        return $this->belongsTo(CaregiverInterview::class, 'caregiver_interview_id');
    }

    public function talkingPoint(): BelongsTo
    {
        return $this->belongsTo(InterviewTalkingPoint::class, 'talking_point_id');
    }
}
