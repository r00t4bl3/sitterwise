<?php

namespace App\Models;

use App\Models\Traits\Phone;
use Database\Factories\BroadcastMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BroadcastMessage extends Model
{
    /** @use HasFactory<BroadcastMessageFactory> */
    use HasFactory, Phone;

    protected $fillable = [
        'broadcast_id',
        'caregiver_id',
        'phone_number',
        'message_body',
        'twilio_message_sid',
        'status',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(SmsBroadcast::class, 'broadcast_id');
    }

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(Caregiver::class);
    }

    protected function getPhoneColumns(): array
    {
        return ['phone_number'];
    }
}
