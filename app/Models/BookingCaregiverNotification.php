<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingCaregiverNotification extends Model
{
    use HasFactory;

    protected $table = 'booking_caregiver_notifications';

    protected $fillable = [
        'booking_id',
        'caregiver_id',
        'notified_at',
        'viewed_at',
        'responded_at',
        'claimed',
    ];

    protected $casts = [
        'notified_at' => 'datetime',
        'viewed_at' => 'datetime',
        'responded_at' => 'datetime',
        'claimed' => 'boolean',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(Caregiver::class);
    }
}
