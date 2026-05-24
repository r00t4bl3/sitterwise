<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CaregiverEducation extends Model
{
    use SoftDeletes;

    protected $table = 'caregiver_educations';

    protected $fillable = [
        'caregiver_id',
        'education_type',
        'school_name',
        'graduation_year',
        'degree',
    ];

    protected $casts = [
        'graduation_year' => 'integer',
    ];

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(Caregiver::class);
    }
}
