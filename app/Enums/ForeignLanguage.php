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

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function toArray(): array
    {
        return array_map(fn (self $case) => [
            'value' => $case->value,
            'label' => $case->label(),
        ], self::cases());
    }
}
