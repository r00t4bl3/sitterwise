<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingAddress extends Model
{
    use HasFactory;

    protected $table = 'booking_addresses';

    protected $fillable = [
        'booking_id',
        'line1',
        'line2',
        'city',
        'state',
        'zip',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
