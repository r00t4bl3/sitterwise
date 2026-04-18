<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BookingRating extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPE_CLIENT_TO_CAREGIVER = 'client_to_caregiver';

    public const TYPE_CAREGIVER_TO_CLIENT = 'caregiver_to_client';

    protected $fillable = [
        'booking_id',
        'rater_id',
        'ratable_id',
        'ratable_type',
        'rating',
        'comment',
    ];

    protected $casts = [
        'rating' => 'decimal:2',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function rater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rater_id');
    }

    public function ratable(): MorphTo
    {
        return $this->morphTo();
    }
}
