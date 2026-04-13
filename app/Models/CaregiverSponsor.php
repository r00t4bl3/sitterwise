<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CaregiverSponsor extends Model
{
    use SoftDeletes;

    protected $table = 'caregiver_sponsors';

    protected $fillable = [
        'caregiver_id',
        'first_name',
        'last_name',
        'email',
    ];

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(Caregiver::class);
    }
}
