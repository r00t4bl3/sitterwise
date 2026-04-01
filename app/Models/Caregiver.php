<?php
namespace App\Models;

use Database\Factories\CaregiverFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Caregiver extends Model
{
    use HasFactory;

    protected static function newFactory(): CaregiverFactory
    {
        return CaregiverFactory::new ();
    }

    // Sync system's user name with caregiver's first and last name
    protected static function booted(): void
    {
        static::saved(function (Caregiver $caregiver) {
            if ($caregiver->user) {
                $caregiver->user->update(['name' => "{$caregiver->first_name} {$caregiver->last_name}"]);
            }
        });
    }

    protected $fillable = [
        'user_id',
        'status_id',
        'first_name',
        'last_name',
        'phone',
        'address',
        'date_of_birth',
        'rating',
        'biography',
        'notes',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'rating'        => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(CaregiverStatus::class, 'status_id');
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

    public function availabilities(): HasMany
    {
        return $this->hasMany(Availability::class)->orderBy('date');
    }

    public function bookings(): HasMany
    {
        // return $this->hasManyThrough(Booking::class);
        return $this->hasMany(Booking::class);
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}