<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'client_first_name',
        'client_last_name',
        'client_phone',
        'client_email',
        'children',
        'pets',
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
        'other_adults_present',
        'special_needs_notes',
        'emergency_instructions',
        'total_amount',
        'reimbursement',
        'tip',
        'payment_status',
        'stripe_payment_intent_id',
        'actual_amount',
        'charge_attempt_count',
        'last_charge_attempt_at',
        'requires_payment',
    ];

    protected $casts = [
        'start_datetime' => 'datetime',
        'end_datetime' => 'datetime',
        'last_charge_attempt_at' => 'datetime',
        'special_considerations' => 'array',
        'sitter_preferences' => 'array',
        'children' => 'array',
        'pets' => 'array',
        'total_amount' => 'decimal:2',
        'reimbursement' => 'decimal:2',
        'tip' => 'decimal:2',
        'actual_amount' => 'decimal:2',
        'charge_attempt_count' => 'integer',
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

    public function payments(): HasMany
    {
        return $this->hasMany(ClientPayment::class);
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
