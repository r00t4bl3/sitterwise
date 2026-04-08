<?php

namespace App\Models;

use Database\Factories\ClientPaymentMethodFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientPaymentMethod extends Model
{
    use HasFactory;

    protected static function newFactory(): ClientPaymentMethodFactory
    {
        return ClientPaymentMethodFactory::new();
    }

    protected $fillable = [
        'client_id',
        'provider',
        'provider_method_id',
        'brand',
        'last4',
        'exp_month',
        'exp_year',
        'status',
        'metadata',
        'is_default',
    ];

    protected $casts = [
        'metadata' => 'array',
        'exp_month' => 'integer',
        'exp_year' => 'integer',
        'is_default' => 'boolean',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
