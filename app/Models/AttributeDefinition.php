<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AttributeDefinition extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'type',
        'entity_type',
        'options',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'options' => 'array',
        'is_active' => 'boolean',
    ];

    public function caregivers(): BelongsToMany
    {
        return $this->belongsToMany(
            Caregiver::class,
            'entity_attribute_values',
            'entity_id'
        )
            ->withPivot('value', 'entity_type')
            ->withTimestamps()
            ->wherePivot('entity_type', 'caregiver');
    }

    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(
            Client::class,
            'entity_attribute_values',
            'entity_id'
        )
            ->withPivot('value', 'entity_type')
            ->withTimestamps()
            ->wherePivot('entity_type', 'client');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    public function scopeForCaregivers($query)
    {
        return $query->whereIn('entity_type', ['caregiver', 'both']);
    }

    public function scopeForClients($query)
    {
        return $query->whereIn('entity_type', ['client', 'both']);
    }
}
