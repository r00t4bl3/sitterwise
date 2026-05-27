<?php

namespace App\Models;

use App\Enums\CaregiverStatus;
use Database\Factories\CaregiverFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Caregiver extends Model
{
    use HasFactory, SoftDeletes;

    protected static function newFactory(): CaregiverFactory
    {
        return CaregiverFactory::new();
    }

    // Sync system's user name with caregiver's first and last name
    protected static function booted(): void
    {
        static::creating(function (Caregiver $caregiver) {
            if (empty($caregiver->slug)) {
                $caregiver->slug = static::generateSlug(
                    "{$caregiver->first_name} {$caregiver->last_name}"
                );
            }
        });

        static::saved(function (Caregiver $caregiver) {
            if ($caregiver->user) {
                $caregiver->user->update(['name' => "{$caregiver->first_name} {$caregiver->last_name}"]);
            }
        });
    }

    private static function generateSlug(string $name): string
    {
        $parts = explode(' ', $name, 2);
        $firstName = $parts[0] ?? '';
        $lastName = $parts[1] ?? '';

        $lastInitial = $lastName
            ? Str::slug(mb_substr($lastName, 0, 1))
            : '';

        $baseSlug = Str::slug($firstName).'-'.$lastInitial;

        if (empty($baseSlug) || $baseSlug === '-') {
            $baseSlug = Str::slug($name);
        }

        $originalSlug = $baseSlug;
        $counter = 2;

        while (self::where('slug', $baseSlug)->exists()) {
            $baseSlug = $originalSlug.'-'.$counter;
            $counter++;
        }

        return $baseSlug;
    }

    protected $fillable = [
        'user_id',
        'status',
        'first_name',
        'last_name',
        'slug',
        'phone',
        'address_line1',
        'address_line2',
        'address_city',
        'address_state',
        'address_zip',
        'date_of_birth',
        'rating',
        'admin_rating',
        'biography',
        'notes',
        'stripe_account_id',
        'stripe_charges_enabled',
        'education_level',
        'languages',
        'metadata',
        'sms_opted_out',
        'status_token',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'rating' => 'decimal:2',
        'admin_rating' => 'decimal:2',
        'languages' => 'array',
        'metadata' => 'array',
        'sms_opted_out' => 'boolean',
        'status' => CaregiverStatus::class,
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

    public function educations(): HasMany
    {
        return $this->hasMany(CaregiverEducation::class);
    }

    public function experiences(): HasMany
    {
        return $this->hasMany(CaregiverExperience::class);
    }

    public function references(): HasMany
    {
        return $this->hasMany(CaregiverReference::class);
    }

    public function sponsors(): HasMany
    {
        return $this->hasMany(CaregiverSponsor::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function certifications(): BelongsToMany
    {
        return $this->belongsToMany(
            CertificationType::class,
            'caregiver_certifications'
        )
            ->withPivot('expiration_date', 'file_path', 'verified_at', 'notes')
            ->withTimestamps();
    }

    public function specialtyTypes(): BelongsToMany
    {
        return $this->belongsToMany(
            SpecialtyType::class,
            'caregiver_specialties'
        )->withTimestamps();
    }

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(
            Location::class,
            'caregiver_locations'
        )
            ->withPivot('is_preferred')
            ->withTimestamps();
    }

    public function preferredLocations(): BelongsToMany
    {
        return $this->locations()->wherePivot('is_preferred', true);
    }

    public function willingLocations(): BelongsToMany
    {
        return $this->locations()->wherePivot('is_preferred', false);
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
            ->wherePivot('entity_type', 'caregiver');
    }

    public function blockedClients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'client_blocked_caregivers');
    }

    public function availabilities(): HasMany
    {
        return $this->hasMany(Availability::class)->orderBy('date');
    }

    public function bookings(): HasMany
    {
        // return $this->hasManyThrough(Booking::class);
        return $this->hasMany(Booking::class);
    }

    public function payoutMethods(): HasMany
    {
        return $this->hasMany(CaregiverPayoutMethod::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(CaregiverPayout::class);
    }

    public function bookingNotifications(): HasMany
    {
        return $this->hasMany(BookingCaregiverNotification::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(CaregiverApplication::class);
    }

    public function caregiverPauses(): HasMany
    {
        return $this->hasMany(CaregiverPause::class);
    }

    public function activePause(): HasOne
    {
        return $this->hasOne(CaregiverPause::class)->whereNull('resumed_at');
    }

    public function onboardingChecklistItems(): HasMany
    {
        return $this->hasMany(OnboardingChecklistItem::class);
    }

    public function referenceRequests(): HasMany
    {
        return $this->hasMany(ReferenceRequest::class);
    }

    public function agreements(): HasMany
    {
        return $this->hasMany(CaregiverAgreement::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(CaregiverAssignment::class);
    }

    public function assignedBookings(): BelongsToMany
    {
        return $this->belongsToMany(Booking::class, 'caregiver_assignments')
            ->withPivot('resolution', 'assigned_at', 'resolution_at', 'resolution_note', 'late_arrival_flag')
            ->withTimestamps();
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function scopeActiveForSms($query)
    {
        return $query
            ->where('status', CaregiverStatus::Active)
            ->whereNotNull('phone')
            ->where('phone', '<>', '')
            ->where('sms_opted_out', false);
    }
}
