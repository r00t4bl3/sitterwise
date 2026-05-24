<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
