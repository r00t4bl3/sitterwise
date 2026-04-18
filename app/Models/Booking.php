<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use HasFactory, SoftDeletes;

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Booking $booking) {
            $booking->calculateTotalWorkingHours();
        });

        static::updating(function (Booking $booking) {
            if ($booking->isDirty(['start_datetime', 'end_datetime'])) {
                $booking->calculateTotalWorkingHours();
            }
        });
    }

    public function calculateTotalWorkingHours(): void
    {
        if ($this->start_datetime && $this->end_datetime) {
            $start = $this->start_datetime instanceof Carbon ? $this->start_datetime : Carbon::parse($this->start_datetime);
            $end = $this->end_datetime instanceof Carbon ? $this->end_datetime : Carbon::parse($this->end_datetime);
            $this->total_working_hour = $start->diffInMinutes($end) / 60;
        }
    }

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
        'reserved_by',
        'reservation_expires_at',
        'confirmed_by',
        'confirmed_at',
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
        'reimbursement_description',
        'bonus',
        'tip',
        'payment_status',
        'stripe_payment_intent_id',
        'actual_amount',
        'charge_attempt_count',
        'last_charge_attempt_at',
        'requires_payment',
    ];

    public function casts(): array
    {
        return [
            'start_datetime' => 'datetime',
            'end_datetime' => 'datetime',
            'reservation_expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'last_charge_attempt_at' => 'datetime',
            'special_considerations' => 'array',
            'sitter_preferences' => 'array',
            'children' => 'array',
            'pets' => 'array',
            'total_amount' => 'decimal:2',
            'reimbursement' => 'decimal:2',
            'reimbursement_description' => 'string',
            'bonus' => 'decimal:2',
            'tip' => 'decimal:2',
            'actual_amount' => 'decimal:2',
            'charge_attempt_count' => 'integer',
            'requires_payment' => 'boolean',
        ];
    }

    public function ratings()
    {
        return $this->hasMany(BookingRating::class, 'booking_id');
    }

    public function clientRating()
    {
        return $this->hasOne(BookingRating::class, 'booking_id')
            ->where('ratable_type', Client::class);
    }

    public function caregiverRating()
    {
        return $this->hasOne(BookingRating::class, 'booking_id')
            ->where('ratable_type', Caregiver::class);
    }

    public function getClientRatingAttribute(): ?array
    {
        if (! $this->relationLoaded('clientRating')) {
            return null;
        }

        $rating = $this->clientRating;

        return $rating ? [
            'id' => $rating->id,
            'rating' => $rating->rating,
            'comment' => $rating->comment,
        ] : null;
    }

    public function getCaregiverRatingAttribute(): ?array
    {
        if (! $this->relationLoaded('caregiverRating')) {
            return null;
        }

        $rating = $this->caregiverRating;

        return $rating ? [
            'id' => $rating->id,
            'rating' => $rating->rating,
            'comment' => $rating->comment,
        ] : null;
    }

    public function bookingGroup()
    {
        return $this->belongsTo(BookingGroup::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function caregiver()
    {
        return $this->belongsTo(Caregiver::class);
    }

    public function availability()
    {
        return $this->belongsTo(Availability::class);
    }

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    public function address()
    {
        return $this->belongsTo(ClientAddress::class, 'address_id');
    }

    public function payments()
    {
        return $this->hasMany(ClientPayment::class);
    }

    public function attributeDefinitions()
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

    public function caregiverNotifications()
    {
        return $this->hasMany(BookingCaregiverNotification::class);
    }

    public function notifiedCaregivers()
    {
        return $this->belongsToMany(Caregiver::class, 'booking_caregiver_notifications')
            ->withPivot('notified_at', 'viewed_at', 'responded_at', 'claimed')
            ->withTimestamps();
    }

    public function reservedCaregiver()
    {
        return $this->belongsTo(Caregiver::class, 'reserved_by');
    }

    public function confirmedCaregiver()
    {
        return $this->belongsTo(Caregiver::class, 'confirmed_by');
    }
}
