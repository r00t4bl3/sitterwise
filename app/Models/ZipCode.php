<?php

namespace App\Models;

use App\Services\CaregiverRecommendation\LocationMatcher;
use Database\Factories\ZipCodeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class ZipCode extends Model
{
    /** @use HasFactory<ZipCodeFactory> */
    use HasFactory;

    private const SERVICEABLE_CACHE_KEY = 'zip_codes.serviceable';

    protected $fillable = [
        'zip_code',
        'area',
        'location_id',
    ];

    protected static function booted(): void
    {
        $forget = fn () => Cache::forget(self::SERVICEABLE_CACHE_KEY);
        static::saved($forget);
        static::deleted($forget);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * The set of serviced 5-digit zip codes (the coverage list). Cached outside
     * the testing env (where the cache could leak across tests).
     *
     * @return list<string>
     */
    public static function serviceableZips(): array
    {
        if (app()->environment('testing')) {
            return static::computeServiceableZips();
        }

        return Cache::remember(
            self::SERVICEABLE_CACHE_KEY,
            now()->addHour(),
            fn (): array => static::computeServiceableZips(),
        );
    }

    /**
     * @return list<string>
     */
    private static function computeServiceableZips(): array
    {
        $matcher = app(LocationMatcher::class);

        return static::query()
            ->pluck('zip_code')
            ->map(fn ($zip) => $matcher->normalizeZip($zip))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Whether an address zip falls within our serviced area. Fails open when no
     * coverage list is configured (empty table) so nothing is blocked by
     * misconfiguration.
     */
    public static function isServiceable(?string $zip): bool
    {
        $serviceable = static::serviceableZips();

        if ($serviceable === []) {
            return true;
        }

        $normalized = app(LocationMatcher::class)->normalizeZip($zip);

        return $normalized !== null && in_array($normalized, $serviceable, true);
    }
}
