<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CaregiverExperience extends Model
{
    use SoftDeletes;

    protected $table = 'caregiver_experiences';

    protected $fillable = [
        'caregiver_id',
        'sequence',
        'start_date',
        'end_date',
        'details',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(Caregiver::class);
    }
}
