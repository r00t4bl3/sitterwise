<?php
namespace App\Models;

use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use HasFactory;

    protected static function newFactory(): ClientFactory
    {
        return ClientFactory::new ();
    }

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'email',
        'cell_phone',
        'client_type',
        'corporate_id',
        'how_did_you_hear',
        'sitter_preferences',
        'other_adults_in_home',
        'medical_info',
        'emergency_instructions',
        'caregiver_notes',
    ];

    protected $casts = [
        'sitter_preferences' => 'array',
        'special_needs'      => 'boolean',
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
}