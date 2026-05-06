<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Availability extends Model
{
    use HasFactory;

    protected $fillable = [
        'caregiver_id',
        'date',
        'time_slots',
        'specific_time',
    ];

    protected $casts = [
        'date' => 'date',
        'time_slots' => 'array',
    ];

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(Caregiver::class);
    }

    public function scopeInTheFuture($query)
    {
        return $query->where('date', '>=', now()->toDateString());
    }
}
