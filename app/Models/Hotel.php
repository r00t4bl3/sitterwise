<?php

namespace App\Models;

use App\Models\Traits\Phone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Hotel extends Model
{
    use HasFactory, Phone;

    protected $fillable = [
        'name',
        'line1',
        'line2',
        'city',
        'state',
        'zip',
        'parking_instructions',
        'hourly_rate',
        'resort_fee',
        'contact_name',
        'contact_phone',
        'admin_notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'hourly_rate' => 'decimal:2',
        'resort_fee' => 'decimal:2',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('name');
    }

    protected function getPhoneColumns(): array
    {
        return ['contact_phone'];
    }
}
