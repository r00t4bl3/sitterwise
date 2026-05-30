<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Model;

trait Phone
{
    public static function bootPhone(): void
    {
        static::saving(function (Model $model) {
            foreach ($model->getPhoneColumns() as $column) {
                $value = $model->{$column};

                if (is_string($value) && $value !== '') {
                    $model->{$column} = static::normalizePhone($value);
                }
            }
        });
    }

    protected function getPhoneColumns(): array
    {
        return ['phone'];
    }

    public static function normalizePhone(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $digits = preg_replace('/[^0-9]/', '', $value);

        if (strlen($digits) === 10) {
            return '+1'.$digits;
        }

        if (strlen($digits) === 11 && $digits[0] === '1') {
            return '+'.$digits;
        }

        return '+'.$digits;
    }
}
