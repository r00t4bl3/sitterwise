<?php

use App\Models\Location;
use App\Models\ZipCode;
use App\Services\CaregiverRecommendation\LocationMatcher;
use Database\Seeders\ZipCodeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->matcher = app(LocationMatcher::class);
});

describe('LocationMatcher zip resolution', function () {
    it('resolves a known zip to its region and area', function () {
        $region = Location::factory()->create(['name' => 'North County']);
        ZipCode::factory()->create([
            'zip_code' => '92008',
            'area' => 'Carlsbad Village',
            'location_id' => $region->id,
        ]);

        expect($this->matcher->getLocationIdForZip('92008'))->toBe($region->id)
            ->and($this->matcher->getAreaForZip('92008'))->toBe('Carlsbad Village');
    });

    it('returns null for an unknown zip', function () {
        Location::factory()->create();

        expect($this->matcher->getLocationIdForZip('99999'))->toBeNull()
            ->and($this->matcher->getAreaForZip('99999'))->toBeNull();
    });

    it('returns null for a blank or invalid zip', function () {
        expect($this->matcher->getLocationIdForZip(null))->toBeNull()
            ->and($this->matcher->getLocationIdForZip(''))->toBeNull()
            ->and($this->matcher->getLocationIdForZip('abc'))->toBeNull();
    });

    it('normalizes ZIP+4 and stray characters to the 5-digit zip', function () {
        $region = Location::factory()->create();
        ZipCode::factory()->create([
            'zip_code' => '92101',
            'area' => 'Core-Columbia',
            'location_id' => $region->id,
        ]);

        expect($this->matcher->normalizeZip('92101-1234'))->toBe('92101')
            ->and($this->matcher->getLocationIdForZip('92101-1234'))->toBe($region->id)
            ->and($this->matcher->getLocationIdForZip(' 92101 '))->toBe($region->id);
    });

    it('resolves null region for an unassigned zip', function () {
        ZipCode::factory()->create([
            'zip_code' => '92055',
            'area' => 'Camp Pendleton',
            'location_id' => null,
        ]);

        expect($this->matcher->getLocationIdForZip('92055'))->toBeNull()
            ->and($this->matcher->getAreaForZip('92055'))->toBe('Camp Pendleton');
    });
});

describe('ZipCodeSeeder', function () {
    it('seeds zip codes from the dataset and maps them to regions', function () {
        $this->seed(ZipCodeSeeder::class);

        expect(ZipCode::count())->toBeGreaterThan(100);

        // A known dataset row: 91901 Alpine -> South County.
        $zip = ZipCode::where('zip_code', '91901')->first();
        expect($zip)->not->toBeNull()
            ->and($zip->area)->toBe('Alpine')
            ->and($zip->location->name)->toBe('South County');
    });

    it('does not create duplicate regions from whitespace and is idempotent', function () {
        $this->seed(ZipCodeSeeder::class);
        $regionsAfterFirst = Location::whereIn('name', ['North County', 'South County'])->count();

        $this->seed(ZipCodeSeeder::class);

        expect(Location::whereIn('name', ['North County', 'South County'])->count())
            ->toBe($regionsAfterFirst)
            ->and(Location::where('name', ' South County')->exists())->toBeFalse();
    });
});
