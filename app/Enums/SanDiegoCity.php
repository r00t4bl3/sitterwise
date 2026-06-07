<?php

namespace App\Enums;

enum SanDiegoCity: string
{
    case Carlsbad = 'Carlsbad';
    case ChulaVista = 'Chula Vista';
    case Coronado = 'Coronado';
    case DelMar = 'Del Mar';
    case DowntownSanDiego = 'Downtown San Diego';
    case ElCajon = 'El Cajon';
    case Encinitas = 'Encinitas';
    case Escondido = 'Escondido';
    case ImperialBeach = 'Imperial Beach';
    case LaJolla = 'La Jolla';
    case LaMesa = 'La Mesa';
    case LemonGrove = 'Lemon Grove';
    case MissionValley = 'Mission Valley';
    case NationalCity = 'National City';
    case Oceanside = 'Oceanside';
    case PacificBeach = 'Pacific Beach';
    case Poway = 'Poway';
    case RanchoSantaFe = 'Rancho Santa Fe';
    case SanMarcos = 'San Marcos';
    case SanYsidro = 'San Ysidro';
    case Santee = 'Santee';
    case SolanaBeach = 'Solana Beach';
    case Vista = 'Vista';

    public static function labels(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
