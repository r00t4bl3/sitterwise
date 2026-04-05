<?php

namespace App\Enums;

enum ClientType: string
{
    case Resident = 'resident';
    case Vacationer = 'vacationer';
    case Invoiced = 'invoiced';

    public function label(): string
    {
        return match ($this) {
            self::Resident => 'San Diego Resident',
            self::Vacationer => 'Vacationer',
            self::Invoiced => 'Invoiced',
        };
    }
}
