<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Read/write access to the DB-backed, web-editable application settings store.
 * Values are cached as a single map and re-read only when a Setting is written.
 */
class Settings
{
    private const CACHE_KEY = 'settings.map';

    /**
     * Get a setting's typed value, or $default when the key is absent.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $map = self::map();

        return array_key_exists($key, $map) ? $map[$key] : $default;
    }

    /**
     * Persist a new value for an existing setting (cast back to its stored form).
     */
    public static function set(string $key, mixed $value): void
    {
        $setting = Setting::where('key', $key)->firstOrFail();
        $setting->update(['value' => self::encode($value, $setting->type)]);
    }

    /**
     * The full key => typed-value map (cached until a Setting is written).
     *
     * @return array<string, mixed>
     */
    public static function map(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => Setting::query()
            ->get(['key', 'value', 'type'])
            ->mapWithKeys(fn (Setting $s) => [$s->key => self::cast($s->value, $s->type)])
            ->all());
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Cast a stored string value to its declared type.
     */
    public static function cast(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($value, true),
            default => $value,
        };
    }

    private static function encode(mixed $value, string $type): string
    {
        return match ($type) {
            'bool' => $value ? '1' : '0',
            'json' => json_encode($value),
            default => (string) $value,
        };
    }
}
