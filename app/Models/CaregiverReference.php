<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CaregiverReference extends Model
{
    use SoftDeletes;

    protected $table = 'caregiver_references';

    protected $fillable = [
        'caregiver_id',
        'reference_name',
    ];

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(Caregiver::class);
    }
}
