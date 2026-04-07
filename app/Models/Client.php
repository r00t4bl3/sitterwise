<?php

namespace App\Models;

use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasFactory, SoftDeletes;

    protected static function newFactory(): ClientFactory
    {
        return ClientFactory::new();
    }

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'phone',
        'client_type',
        'corporate_id',
        'how_did_you_hear',
        'sitter_preferences',
        'other_adults_present',
        'emergency_instructions',
        'special_needs_notes',
    ];

    protected $casts = [
        'sitter_preferences' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(ClientAddress::class);
    }

    public function children(): HasMany
    {
        return $this->hasMany(ClientChild::class);
    }

    public function pets(): HasMany
    {
        return $this->hasMany(ClientPet::class);
    }

    public function favoriteCaregivers(): BelongsToMany
    {
        return $this->belongsToMany(Caregiver::class, 'client_favorite_caregivers');
    }

    public function typeChanges(): HasMany
    {
        return $this->hasMany(ClientTypeChange::class);
    }

    public function bookingGroups(): HasMany
    {
        return $this->hasMany(BookingGroup::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function attributes(): BelongsToMany
    {
        return $this->belongsToMany(
            AttributeDefinition::class,
            'entity_attribute_values',
            'entity_id'
        )
            ->withPivot('value', 'entity_type')
            ->withTimestamps()
            ->wherePivot('entity_type', 'client');
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function getSpecialNeedsAttribute(): bool
    {
        return ! empty($this->special_needs_notes);
    }
}
