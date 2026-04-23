<?php

namespace App\Models;

use App\Enums\ServiceType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Booking extends Model
{
    use HasFactory, SoftDeletes;

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Booking $booking) {
            if (empty($booking->ulid)) {
                $booking->ulid = Str::ulid();
            }
            $booking->calculateTotalWorkingHours();
            $booking->calculateHourlyRate();
            $booking->calculateTotalAmount();
        });

        static::updating(function (Booking $booking) {
            // if ($booking->isDirty(['start_datetime', 'end_datetime'])) {
            $booking->calculateTotalWorkingHours();
            $booking->calculateHourlyRate();
            $booking->calculateTotalAmount();
            // }
        });
    }

    private function calculateTotalWorkingHours(): void
    {
        if ($this->start_datetime && $this->end_datetime) {
            $start = $this->start_datetime instanceof Carbon ? $this->start_datetime : Carbon::parse($this->start_datetime);
            $end = $this->end_datetime instanceof Carbon ? $this->end_datetime : Carbon::parse($this->end_datetime);
            $this->total_working_hour = $start->diffInMinutes($end) / 60;
        }
    }

    private function calculateHourlyRate(): void
    {
        $maxChildren = PricingRule::where('service_type', $this->service_type)->max('number_of_children');
        $numberOfChildren = min(count($this->children ?? []), $maxChildren ?? 0);

        $query = PricingRule::where('service_type', $this->service_type)
            ->where('number_of_children', $numberOfChildren);

        if ($this->service_type === 'petsitter') {
            $query->where('is_for_pets', ! empty($this->pets));
        }

        $pricingRule = $query->first();

        if ($pricingRule) {
            $this->charge_to_client_hourly = $pricingRule->charge_to_client;
            $this->paid_to_caregiver_hourly = $pricingRule->paid_to_caregiver;
            $this->sitterwise_cut_hourly = $pricingRule->sitterwise_cut;
        } else {
            $this->charge_to_client_hourly = 0.00;
            $this->paid_to_caregiver_hourly = 0.00;
            $this->sitterwise_cut_hourly = 0.00;
        }
    }

    private function calculateTotalAmount(): void
    {
        $this->charge_to_client = round($this->charge_to_client_hourly * $this->total_working_hour, 2);
        $this->paid_to_caregiver = round($this->paid_to_caregiver_hourly * $this->total_working_hour, 2);
        $this->sitterwise_cut = round($this->sitterwise_cut_hourly * $this->total_working_hour, 2);
    }

    public function resolveRouteBinding($value, $field = null)
    {
        return $this->where('id', $value)
            ->orWhere('ulid', $value)
            ->firstOrFail();
    }

    protected $guarded = ['id'];

    protected $appends = [
        'service_type_label',
    ];

    public function casts(): array
    {
        return [
            'start_datetime' => 'datetime:Y-m-d\TH:i:s',
            'end_datetime' => 'datetime:Y-m-d\TH:i:s',
            'reservation_expires_at' => 'datetime:Y-m-d\TH:i:s',
            'confirmed_at' => 'datetime:Y-m-d\TH:i:s',
            'last_charge_attempt_at' => 'datetime:Y-m-d\TH:i:s',
            'special_considerations' => 'array',
            'sitter_preferences' => 'array',
            'children' => 'array',
            'pets' => 'array',
            'total_amount' => 'decimal:2',
            'caregiver_amount' => 'decimal:2',
            'reimbursement' => 'decimal:2',
            'reimbursement_description' => 'string',
            'bonus' => 'decimal:2',
            'tip' => 'decimal:2',
            'actual_amount' => 'decimal:2',
            'charge_attempt_count' => 'integer',
            'requires_payment' => 'boolean',
            'charge_to_client_hourly' => 'decimal:2',
            'paid_to_caregiver_hourly' => 'decimal:2',
            'sitterwise_cut_hourly' => 'decimal:2',
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

    public function getServiceTypeLabelAttribute(): ?string
    {
        if (! $this->service_type) {
            return null;
        }

        return ServiceType::tryFrom($this->service_type)?->label() ?? $this->service_type;
    }
}
