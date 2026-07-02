<?php

use App\Services\ImportUserService;

function invokeImportParser(string $method, array $args): mixed
{
    $ref = new ReflectionMethod(ImportUserService::class, $method);
    $ref->setAccessible(true);

    return $ref->invoke(null, ...$args);
}

describe('import child parsing (#153)', function () {
    test('isJunkChildName flags negation names and null', function () {
        expect(ImportUserService::isJunkChildName('None'))->toBeTrue()
            ->and(ImportUserService::isJunkChildName('n/a'))->toBeTrue()
            ->and(ImportUserService::isJunkChildName('  NONE  '))->toBeTrue()
            ->and(ImportUserService::isJunkChildName(null))->toBeTrue()
            ->and(ImportUserService::isJunkChildName('Alice'))->toBeFalse();
    });

    test('parseChildren returns null for a negation text instead of padding', function () {
        expect(invokeImportParser('parseChildren', ['None', '1']))->toBeNull();
    });

    test('parseChildren keeps real names and drops junk parts', function () {
        expect(invokeImportParser('parseChildren', ['Alice, None, Bob', null]))
            ->toBe([['name' => 'Alice'], ['name' => 'Bob']]);
    });

    test('parseChildEntry rejects a None entry', function () {
        expect(invokeImportParser('parseChildEntry', ['None']))->toBeNull();
    });

    test('parseChildEntry parses a real child', function () {
        expect(invokeImportParser('parseChildEntry', ['Alice (5)']))
            ->toMatchArray(['name' => 'Alice']);
    });
});
