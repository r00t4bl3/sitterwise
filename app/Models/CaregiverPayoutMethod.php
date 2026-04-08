<?php

namespace App\Models;

use Database\Factories\CaregiverPayoutMethodFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaregiverPayoutMethod extends Model
{
    use HasFactory;

    protected static function newFactory(): CaregiverPayoutMethodFactory
    {
        return CaregiverPayoutMethodFactory::new();
    }

    protected $fillable = [
        'caregiver_id',
        'provider',
        'provider_method_id',
        'account_type',
        'bank_name',
        'last4',
        'status',
        'metadata',
        'is_default',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_default' => 'boolean',
    ];

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(Caregiver::class);
    }
}
