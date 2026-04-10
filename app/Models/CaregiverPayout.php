<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaregiverPayout extends Model
{
    use HasFactory;

    protected $fillable = [
        'caregiver_id',
        'caregiver_payout_method_id',
        'amount',
        'currency',
        'status',
        'provider_transfer_id',
        'payout_date',
    ];

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(Caregiver::class);
    }

    public function payoutMethod(): BelongsTo
    {
        return $this->belongsTo(CaregiverPayoutMethod::class, 'caregiver_payout_method_id');
    }
}
