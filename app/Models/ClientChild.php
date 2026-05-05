<?php

namespace App\Models;

use Database\Factories\ClientChildFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientChild extends Model
{
    use HasFactory, SoftDeletes;

    protected static function newFactory(): ClientChildFactory
    {
        return ClientChildFactory::new();
    }

    protected $fillable = [
        'client_id',
        'name',
        'gender',
        'birth_date',
    ];

    protected $casts = [
        'birth_date' => 'date',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function getAgeAttribute(): ?int
    {
        if (! $this->birth_date) {
            return null;
        }

        return $this->birth_date->diffInYears(now());
    }

    public function getBirthMonthAttribute(): ?int
    {
        return $this->birth_date?->month;
    }

    public function getBirthYearAttribute(): ?int
    {
        return $this->birth_date?->year;
    }
}
