<?php

namespace App\Rules;

use App\Support\Settings;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class MinimumBookingDuration implements DataAwareRule, ValidationRule
{
    protected $data = [];

    /**
     * Set the data under validation.
     *
     * @return $this
     */
    public function setData(array $data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $minHours = (int) Settings::get('bookings.minimum_hours', 4);
        $endDateTime = Carbon::parse($value);
        $startDateTime = Carbon::parse($this->data['start_datetime'] ?? null);
        if ($startDateTime->diffInHours($endDateTime) < $minHours) {
            $fail("The booking must be at least {$minHours} hours long.");
        }
    }
}
