<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CertificationType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'expires_required',
        'is_active',
    ];

    protected $casts = [
        'expires_required' => 'boolean',
        'is_active'        => 'boolean',
    ];

    public function caregivers(): BelongsToMany
    {
        return $this->belongsToMany(
            Caregiver::class,
            'caregiver_certifications'
        )
            ->withPivot('expiration_date', 'file_path', 'verified_at', 'notes')
            ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}