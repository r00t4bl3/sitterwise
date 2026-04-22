<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PricingRule extends Model
{
    use HasFactory;

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
