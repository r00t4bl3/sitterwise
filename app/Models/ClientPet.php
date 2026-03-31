<?php

namespace App\Models;

use Database\Factories\ClientPetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientPet extends Model
{
    use HasFactory;

    protected static function newFactory(): ClientPetFactory
    {
        return ClientPetFactory::new();
    }

    protected $fillable = [
        'client_id',
        'name',
        'type',
        'breed',
        'notes',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
