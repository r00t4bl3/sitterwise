<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PricingRule extends Model
{
    use HasFactory;

    /**
     * payment_form value for jobs settled by card via Stripe.
     */
    public const PAYMENT_FORM_STRIPE = 'Stripe';

    /**
     * Whether a job of this service type is billable (client is owed money).
     * Corporate/group invoiced jobs are billable (via invoice); only truly free
     * services (comped, charge_to_client 0) are not. Unknown service types
     * default to billable so we never silently skip collecting.
     */
    public static function requiresPaymentFor(?string $serviceType): bool
    {
        if (blank($serviceType)) {
            return true;
        }

        $query = static::query()->where('service_type', $serviceType);

        if (! $query->exists()) {
            return true;
        }

        return (float) $query->max('charge_to_client') > 0;
    }

    /**
     * The settlement rail (e.g. Stripe, OnPay (Payroll)) for a service type,
     * or null when there is no matching pricing rule.
     */
    public static function paymentFormFor(?string $serviceType): ?string
    {
        if (blank($serviceType)) {
            return null;
        }

        return static::query()->where('service_type', $serviceType)->value('payment_form');
    }

    /**
     * Whether a service type is settled by Stripe card charge.
     */
    public static function isStripeChargedServiceType(?string $serviceType): bool
    {
        return self::requiresPaymentFor($serviceType)
            && self::paymentFormFor($serviceType) === self::PAYMENT_FORM_STRIPE;
    }

    protected $fillable = [
        'service_type',
        'number_of_children',
        'is_for_pets',
        'charge_to_client',
        'charge_to_client_notes',
        'paid_to_caregiver',
        'payment_form',
        'sitterwise_cut',
    ];

    protected $casts = [
        'is_for_pets' => 'boolean',
        'charge_to_client' => 'decimal:2',
        'paid_to_caregiver' => 'decimal:2',
        'sitterwise_cut' => 'decimal:2',
    ];
}
