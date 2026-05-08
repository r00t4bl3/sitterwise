<?php

namespace App\Enums;

enum PetType: string
{
    case Dog = 'dog';
    case Cat = 'cat';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Dog => 'Dog',
            self::Cat => 'Cat',
            self::Other => 'Other',
        };
    }
}
