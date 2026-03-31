<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientTypeChange extends Model
{
    protected $fillable = [
        'client_id',
        'changed_by_admin_id',
        'previous_type',
        'new_type',
        'reason',
        'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_admin_id');
    }
}
