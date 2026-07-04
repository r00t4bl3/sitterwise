<?php

namespace App\Models;

use Database\Factories\ZipCodeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZipCode extends Model
{
    /** @use HasFactory<ZipCodeFactory> */
    use HasFactory;

    protected $fillable = [
        'zip_code',
        'area',
        'location_id',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
