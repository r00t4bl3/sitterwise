<?php

use App\Services\ImportUserService;

it('passes floats through unchanged', function () {
    expect(ImportUserService::normalizeAmount(181.13))->toBe(181.13);
    expect(ImportUserService::normalizeAmount(100.00))->toBe(100.00);
    expect(ImportUserService::normalizeAmount(0.50))->toBe(0.50);
});

it('divides integers >= 1000 by 100', function () {
    expect(ImportUserService::normalizeAmount(18113))->toBe(181.13);
    expect(ImportUserService::normalizeAmount(10000))->toBe(100.00);
    expect(ImportUserService::normalizeAmount(1000))->toBe(10.00);
    expect(ImportUserService::normalizeAmount(50000))->toBe(500.00);
    expect(ImportUserService::normalizeAmount(99999))->toBe(999.99);
});

it('keeps integers < 1000 as dollars', function () {
    expect(ImportUserService::normalizeAmount(999))->toBe(999.0);
    expect(ImportUserService::normalizeAmount(100))->toBe(100.0);
    expect(ImportUserService::normalizeAmount(50))->toBe(50.0);
    expect(ImportUserService::normalizeAmount(5))->toBe(5.0);
    expect(ImportUserService::normalizeAmount(0))->toBe(0.0);
});

it('handles edge case 1000 correctly', function () {
    expect(ImportUserService::normalizeAmount(1000))->toBe(10.00);
});

it('handles edge case 999 correctly', function () {
    expect(ImportUserService::normalizeAmount(999))->toBe(999.0);
});

it('handles very large integers', function () {
    expect(ImportUserService::normalizeAmount(100000))->toBe(1000.00);
    expect(ImportUserService::normalizeAmount(123456))->toBe(1234.56);
});

it('handles negative floats', function () {
    expect(ImportUserService::normalizeAmount(-50.25))->toBe(-50.25);
});

it('handles negative integers', function () {
    expect(ImportUserService::normalizeAmount(-1000))->toBe(-1000.0);
    expect(ImportUserService::normalizeAmount(-500))->toBe(-500.0);
});

it('handles float with cents', function () {
    expect(ImportUserService::normalizeAmount(0.99))->toBe(0.99);
    expect(ImportUserService::normalizeAmount(10.01))->toBe(10.01);
});

it('handles integer 1', function () {
    expect(ImportUserService::normalizeAmount(1))->toBe(1.0);
});

it('does not lose precision', function () {
    $result = ImportUserService::normalizeAmount(10099);
    expect($result)->toBe(100.99);
});
