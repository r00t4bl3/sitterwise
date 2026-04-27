<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'client_id',
        'payment_method_id',
        'amount',
        'currency',
        'status',
        'provider',
        'provider_payment_id',
        'provider_charge_id',
        'paid_at',
        'metadata',
        'error_code',
        'error_message',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(ClientPaymentMethod::class, 'payment_method_id');
    }
}
