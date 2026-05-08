<?php

use App\Enums\PetType;

it('has the correct values', function () {
    expect(PetType::Dog->value)->toBe('dog');
    expect(PetType::Cat->value)->toBe('cat');
    expect(PetType::Other->value)->toBe('other');
});

it('has the correct labels', function () {
    expect(PetType::Dog->label())->toBe('Dog');
    expect(PetType::Cat->label())->toBe('Cat');
    expect(PetType::Other->label())->toBe('Other');
});

it('can be created from string', function () {
    expect(PetType::from('dog'))->toBe(PetType::Dog);
    expect(PetType::from('cat'))->toBe(PetType::Cat);
    expect(PetType::from('other'))->toBe(PetType::Other);
});
