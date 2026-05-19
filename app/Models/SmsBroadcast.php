<?php

namespace App\Models;

use Database\Factories\SmsBroadcastFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SmsBroadcast extends Model
{
    /** @use HasFactory<SmsBroadcastFactory> */
    use HasFactory;

    protected $fillable = [
        'sent_by_user_id',
        'message_body',
        'recipient_count',
    ];

    public function sentByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(BroadcastMessage::class, 'broadcast_id');
    }
}
