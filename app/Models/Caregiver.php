<?php

namespace App\Models;

use Database\Factories\CaregiverFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Caregiver extends Model
{
    use HasFactory;

    protected static function newFactory(): CaregiverFactory
    {
        return CaregiverFactory::new();
    }

    protected $fillable = [
        'user_id',
        'status_id',
        'first_name',
        'last_name',
        'phone',
        'address',
        'profile_photo_path',
        'date_of_birth',
        'rating',
        'biography',
        'notes',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'rating' => 'decimal:2',
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
            'caregiver_attributes'
        )
            ->withPivot('value')
            ->withTimestamps();
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
