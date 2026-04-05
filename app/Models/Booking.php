<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'booking_group_id',
        'client_id',
        'caregiver_id',
        'availability_id',
        'hotel_id',
        'address_id',
        'address_line1',
        'address_line2',
        'address_city',
        'address_state',
        'address_zip',
        'service_type',
        'location_type',
        'rental_platform',
        'start_datetime',
        'end_datetime',
        'status',
        'special_considerations',
        'caregiver_notes',
        'notes_to_sitterwise',
        'admin_notes',
        'corporate_id',
        'sitter_preferences',
        'other_adults',
        'medical_info',
        'emergency_instructions',
        'total_amount',
        'payment_status',
        'requires_payment',
    ];

    protected $casts = [
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
        'special_considerations' => 'array',
        'sitter_preferences' => 'array',
        'total_amount' => 'decimal:2',
        'requires_payment' => 'boolean',
    ];

    public function bookingGroup(): BelongsTo
    {
        return $this->belongsTo(BookingGroup::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(Caregiver::class);
    }

    public function availability(): BelongsTo
    {
        return $this->belongsTo(Availability::class);
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(ClientAddress::class, 'address_id');
    }

    public function attributeDefinitions(): BelongsToMany
    {
        return $this->belongsToMany(
            AttributeDefinition::class,
            'entity_attribute_values',
            'entity_id'
        )
            ->withPivot('value', 'entity_type')
            ->withTimestamps()
            ->wherePivot('entity_type', 'booking');
    }
}
