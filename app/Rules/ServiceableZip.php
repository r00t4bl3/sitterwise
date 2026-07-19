<?php

namespace App\Rules;

use App\Models\ZipCode;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ServiceableZip implements ValidationRule
{
    /**
     * Fail when a provided zip is not within our serviced area (the zip_codes
     * coverage list). Blank values are left to other rules (required/nullable).
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }

        if (! ZipCode::isServiceable((string) $value)) {
            $fail('This address is outside our service area. We currently serve the San Diego area.');
        }
    }
}
