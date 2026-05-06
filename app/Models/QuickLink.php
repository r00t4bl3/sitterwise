<?php

namespace App\Models;

use Database\Factories\QuickLinkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuickLink extends Model
{
    /** @use HasFactory<QuickLinkFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'url',
        'description',
        'icon',
        'sort_order',
        'is_active',
        'is_external',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_external' => 'boolean',
        'sort_order' => 'integer',
    ];
}
