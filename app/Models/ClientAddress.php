<?php

namespace App\Models;

use Database\Factories\ClientAddressFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientAddress extends Model
{
    use HasFactory;

    protected static function newFactory(): ClientAddressFactory
    {
        return ClientAddressFactory::new();
    }

    protected $fillable = [
        'client_id',
        'label',
        'location_type',
        'line1',
        'line2',
        'city',
        'state',
        'zip',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
