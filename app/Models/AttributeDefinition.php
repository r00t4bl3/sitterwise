<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class AttributeDefinition extends Model
{
    use HasFactory;

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

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (AttributeDefinition $attribute) {
            if (empty($attribute->slug) && ! empty($attribute->name)) {
                $attribute->slug = static::generateSlug($attribute->name);
            }
        });
    }

    private static function generateSlug(string $name): string
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $counter = 1;

        $query = self::where('slug', $slug);

        while ($query->exists()) {
            $slug = $originalSlug.'_'.$counter;
            $query = self::where('slug', $slug);
            $counter++;
        }

        return $slug;
    }

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
