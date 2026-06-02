<?php

namespace App\Enums;

enum ForeignLanguage: string
{
    case Spanish = 'spanish';
    case French = 'french';
    case German = 'german';
    case MandarinChinese = 'mandarin_chinese';
    case Cantonese = 'cantonese';
    case Japanese = 'japanese';
    case Korean = 'korean';
    case Tagalog = 'tagalog';
    case Vietnamese = 'vietnamese';
    case Arabic = 'arabic';
    case AmericanSignLanguage = 'american_sign_language';

    public function label(): string
    {
        return match ($this) {
            self::Spanish => 'Spanish',
            self::French => 'French',
            self::German => 'German',
            self::MandarinChinese => 'Mandarin Chinese',
            self::Cantonese => 'Cantonese',
            self::Japanese => 'Japanese',
            self::Korean => 'Korean',
            self::Tagalog => 'Tagalog',
            self::Vietnamese => 'Vietnamese',
            self::Arabic => 'Arabic',
            self::AmericanSignLanguage => 'American Sign Language',
        };
    }
}
