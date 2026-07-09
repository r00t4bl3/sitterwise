<?php

namespace App\Models;

use App\Support\Settings;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'label',
        'description',
        'sort_order',
    ];

    protected static function booted(): void
    {
        // Any write invalidates the cached settings map so reads stay fresh.
        static::saved(fn () => Settings::flush());
        static::deleted(fn () => Settings::flush());
    }

    /**
     * The stored string value cast to its declared type.
     */
    public function castedValue(): mixed
    {
        return Settings::cast($this->value, $this->type);
    }
}
