<?php

namespace App\Http\Requests;

use App\Models\Setting;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route is gated by the super_admin middleware.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Setting keys contain dots (e.g. "lifesaver.hours_unclaimed"), which
        // collide with Laravel's dot-nesting in per-field rules — so the map is
        // validated holistically in withValidator() instead.
        return [
            'settings' => ['required', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $types = Setting::pluck('type', 'key');

            foreach ((array) $this->input('settings', []) as $key => $value) {
                $type = $types[$key] ?? null;

                if ($type === null) {
                    continue; // Unknown key — the controller ignores it.
                }

                $valid = match ($type) {
                    'int' => ctype_digit((string) $value),
                    'float' => is_numeric($value) && (float) $value >= 0,
                    'bool' => is_bool($value) || in_array($value, ['0', '1', 0, 1], true),
                    default => is_scalar($value),
                };

                if (! $valid) {
                    $validator->errors()->add("settings.{$key}", "The value for {$key} is invalid.");
                }
            }
        });
    }
}
