<?php

namespace App\Models;

use Database\Factories\CaregiverStatusFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CaregiverStatus extends Model
{
    /** @use HasFactory<CaregiverStatusFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'color',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function caregivers(): HasMany
    {
        return $this->hasMany(Caregiver::class, 'status_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
