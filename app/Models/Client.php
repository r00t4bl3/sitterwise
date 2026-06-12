<?php

namespace App\Models;

use App\Models\Traits\Phone;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;

class Client extends Model
{
    use HasFactory, Notifiable, Phone, SoftDeletes;

    protected static function newFactory(): ClientFactory
    {
        return ClientFactory::new();
    }

    protected static function booted(): void
    {
        static::saved(function (Client $client) {
            if ($client->user) {
                $client->user->update(['name' => $client->full_name]);
            }
        });
    }

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'biography',
        'phone',
        'client_type',
        'corporate_id',
        'stripe_customer_id',
        'rating',
        'how_did_you_hear',
        'sitter_preferences',
        'other_adults_present',
        'emergency_instructions',
        'special_needs_notes',
        'sms_opted_out',
        'notes',
    ];

    protected $appends = [
        'children_count',
        'pets_count',
    ];

    protected $casts = [
        'sitter_preferences' => 'array',
        'rating' => 'decimal:2',
        'sms_opted_out' => 'boolean',
    ];

    public function ratings(): MorphMany
    {
        return $this->morphMany(BookingRating::class, 'ratable');
    }

    public function receivedRatings(): MorphMany
    {
        return $this->morphMany(BookingRating::class, 'ratable');
    }

    public function recalculateRating(): void
    {
        $average = $this->ratings()
            ->whereNull('deleted_at')
            ->avg('rating') ?: 0;

        $this->update(['rating' => round($average, 2)]);
    }

    public function getChildrenCountAttribute(): int
    {
        return $this->relationLoaded('children') ? $this->children->count() : $this->children()->count();
    }

    public function getPetsCountAttribute(): int
    {
        return $this->relationLoaded('pets') ? $this->pets->count() : $this->pets()->count();
    }

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

    public function blockedCaregivers(): BelongsToMany
    {
        return $this->belongsToMany(Caregiver::class, 'client_blocked_caregivers');
    }

    public function previousCaregivers()
    {
        return Caregiver::select('caregivers.*')
            ->join('bookings', 'bookings.caregiver_id', '=', 'caregivers.id')
            ->join('booking_groups', 'bookings.booking_group_id', '=', 'booking_groups.id')
            ->where('booking_groups.client_id', $this->id)
            ->whereNotNull('bookings.caregiver_id')
            ->whereIn('bookings.status', ['completed', 'confirmed', 'paid'])
            ->groupBy('caregivers.id', 'bookings.caregiver_id')
            ->orderByRaw('MAX(bookings.start_datetime) DESC');
    }

    public function typeChanges(): HasMany
    {
        return $this->hasMany(ClientTypeChange::class);
    }

    public function bookingGroups(): HasMany
    {
        return $this->hasMany(BookingGroup::class);
    }

    public function bookings(): HasManyThrough
    {
        return $this->hasManyThrough(Booking::class, BookingGroup::class);
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(ClientPaymentMethod::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ClientPayment::class);
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

    public function routeNotificationForDatabase(): string
    {
        return 'client-notifications-'.$this->id;
    }

    public function hasPaymentMethod(): bool
    {
        return $this->paymentMethods()->where('status', 'active')->exists();
    }
}
