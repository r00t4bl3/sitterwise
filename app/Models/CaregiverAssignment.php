<?php

namespace App\Models;

use App\Enums\AssignmentResolution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaregiverAssignment extends Model
{
    protected $fillable = [
        'caregiver_id',
        'booking_id',
        'assigned_at',
        'resolution',
        'resolution_at',
        'resolution_note',
        'late_arrival_flag',
        'late_arrival_note',
        'excused_by',
        'excused_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'resolution_at' => 'datetime',
        'excused_at' => 'datetime',
        'late_arrival_flag' => 'boolean',
    ];

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(Caregiver::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function excusedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'excused_by');
    }

    public function resolve(AssignmentResolution $resolution, ?string $note = null): void
    {
        $this->update([
            'resolution' => $resolution->value,
            'resolution_at' => now(),
            'resolution_note' => $note ?? $this->resolution_note,
        ]);
    }

    public function scopeUnresolved($query)
    {
        return $query->whereNull('resolution');
    }

    public function scopeWithResolution($query, AssignmentResolution $resolution)
    {
        return $query->where('resolution', $resolution->value);
    }
}
