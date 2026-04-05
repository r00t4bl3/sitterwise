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
        'birth_month',
        'birth_year',
    ];

    protected $casts = [];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function getAgeAttribute(): ?int
    {
        if (! $this->birth_month || ! $this->birth_year) {
            return null;
        }

        $now = now();
        $age = $now->year - $this->birth_year;
        if ($now->month < $this->birth_month || ($now->month == $this->birth_month && $now->day < 1)) {
            $age--;
        }

        return $age;
    }
}
