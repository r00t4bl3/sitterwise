<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\ServiceType;
use App\Enums\SitterPreference;
use App\Enums\SpecialConsideration;
use App\Models\Traits\Phone;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class Booking extends Model
{
    use HasFactory, Phone, SoftDeletes;

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
            $booking->calculateSpecialConsiderations();
        });

        static::updating(function (Booking $booking) {
            if ($booking->isDirty(['start_datetime', 'end_datetime'])) {
                $booking->calculateTotalWorkingHours();
            }

            if ($booking->isDirty(['service_type', 'children', 'pets'])) {
                $booking->calculateHourlyRate();
            }

            $booking->calculateTotalAmount();

            if ($booking->isDirty(['sitter_preferences', 'pets', 'other_adults_present'])) {
                $booking->calculateSpecialConsiderations();
            }
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
        if ($this->status === BookingStatus::Cancelled->value) {
            $this->charge_to_client = 0;
            $this->paid_to_caregiver = 0;
            $this->sitterwise_cut = 0;
            $this->total_service_amount = 0;
            $this->total_amount = 0;

            return;
        }

        $this->charge_to_client = round($this->charge_to_client_hourly * $this->total_working_hour, 2);
        $this->paid_to_caregiver = round($this->paid_to_caregiver_hourly * $this->total_working_hour, 2);
        $this->sitterwise_cut = round($this->sitterwise_cut_hourly * $this->total_working_hour, 2);

        $reimbursement = (float) ($this->getAttribute('reimbursement') ?? 0);
        $bonus = (float) ($this->getAttribute('bonus') ?? 0);
        $tip = (float) ($this->getAttribute('tip') ?? 0);

        $this->total_service_amount = round($this->charge_to_client + $reimbursement + $bonus, 2);
        $this->total_amount = round($this->total_service_amount + $tip, 2);
    }

    public function calculateSpecialConsiderations(): void
    {
        $considerations = collect();

        foreach ($this->sitter_preferences ?? [] as $value) {
            $preference = SitterPreference::tryFrom($value);
            if ($preference) {
                $considerations->push($preference->toSpecialConsideration()->value);
            }
        }

        foreach ($this->pets ?? [] as $pet) {
            $type = strtolower($pet['type'] ?? '');
            if ($type === 'dog') {
                $considerations->push(SpecialConsideration::FamilyHasDogsOnsite->value);
            } elseif ($type === 'cat') {
                $considerations->push(SpecialConsideration::FamilyHasCatsOnsite->value);
            }
        }

        if (! empty($this->other_adults_present)) {
            $considerations->push(SpecialConsideration::ParentWillBePresent->value);
        }

        $this->special_considerations = $considerations->unique()->values()->toArray();
    }

    public function resolveRouteBinding($value, $field = null)
    {
        if (is_numeric($value)) {
            return $this->where('id', $value)->firstOrFail();
        }

        return $this->where('ulid', $value)->firstOrFail();
    }

    protected $guarded = ['id'];

    protected $appends = [
        'service_type_label',
    ];

    public function setStartDatetimeAttribute($value): void
    {
        $this->attributes['start_datetime'] = $this->convertToUtc($value);
    }

    public function setEndDatetimeAttribute($value): void
    {
        $this->attributes['end_datetime'] = $this->convertToUtc($value);
    }

    public function setConfirmedAtAttribute($value): void
    {
        $this->attributes['confirmed_at'] = $this->convertToUtc($value);
    }

    public function setCancelledAtAttribute($value): void
    {
        $this->attributes['cancelled_at'] = $this->convertToUtc($value);
    }

    private function convertToUtc(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->copy()->setTimezone('UTC')->format('Y-m-d H:i:s');
        }

        return Carbon::parse($value, 'America/Los_Angeles')
            ->setTimezone('UTC')
            ->format('Y-m-d H:i:s');
    }

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
            'children_notes' => 'string',
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
            'total_service_amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (Booking $booking) {
            if ($booking->wasChanged('caregiver_id') && $booking->caregiver_id) {
                $booking->assignments()->firstOrCreate(
                    ['caregiver_id' => $booking->caregiver_id],
                    ['assigned_at' => now()],
                );
            }
        });
    }

    public function ratings(): HasMany
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

        $rating = $this->getRelation('clientRating');

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

        $rating = $this->getRelation('caregiverRating');

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

    public function assignments(): HasMany
    {
        return $this->hasMany(CaregiverAssignment::class);
    }

    public function scopeInFuture($query)
    {
        return $query->where('start_datetime', '>', now()->startOfDay());
    }

    public function scopeInToday($query)
    {
        return $query->whereBetween('start_datetime', [now()->startOfDay(), now()->endOfDay()]);
    }

    public function getServiceTypeLabelAttribute(): ?string
    {
        if (! $this->service_type) {
            return null;
        }

        return ServiceType::tryFrom($this->service_type)?->label() ?? $this->service_type;
    }

    /**
     * Get the dynamic data for SendGrid email templates.
     */
    public function toEmailData(): array
    {
        $start = $this->start_datetime instanceof Carbon
            ? $this->start_datetime->copy()->setTimezone('America/Los_Angeles')
            : Carbon::parse($this->start_datetime)->setTimezone('America/Los_Angeles');
        $end = $this->end_datetime instanceof Carbon
            ? $this->end_datetime->copy()->setTimezone('America/Los_Angeles')
            : Carbon::parse($this->end_datetime)->setTimezone('America/Los_Angeles');

        $childrenCount = count($this->children ?? []);
        $childrenSummary = collect($this->children ?? [])
            ->map(fn ($c) => ($c['name'] ?? 'Child').' ('.(isset($c['birth_year']) ? now()->year - $c['birth_year'] : '?').'yr)')
            ->join(', ');

        $clientName = ($this->client?->first_name ?? $this->client_first_name).' '.($this->client?->last_name ?? $this->client_last_name);

        return [
            'booking_id' => $this->id,
            'job_id' => $this->id,
            'ulid' => $this->ulid,
            'client_first_name' => $this->client?->first_name ?? $this->client_first_name,
            'client_name' => $clientName,
            'client_email' => $this->client?->user?->email ?? $this->client_email,
            'client_phone' => $this->client?->phone ?? $this->client_phone,
            'service_requested' => $this->service_type_label,
            'service_name' => $this->service_type_label,
            'date' => $start->format('l, F j, Y'),
            'date_times' => $start->format('l, F j, Y'),
            'service_date_pretty' => $start->format('D, M j, Y'),
            'start_time' => $start->format('g:i A'),
            'end_time' => $end->format('g:i A'),
            'service_time' => $start->format('g:i A'),
            'service_time_range' => $start->format('g:i A').' - '.$end->format('g:i A'),
            'kids_count' => $childrenCount.' '.Str::plural('child', $childrenCount),
            'children_summary' => $childrenSummary ?: 'None',
            'location' => $this->hotel?->name ?? $this->address_line1,
            'address' => trim($this->address_line1.' '.$this->address_line2.', '.$this->address_city.', '.$this->address_state.' '.$this->address_zip),
            'hotel_name' => $this->hotel?->name ?? 'N/A',
            'service_hotel' => $this->hotel?->name ?? 'N/A',
            'is_hotel' => $this->location_type === 'hotel' ? $this->hotel?->name : false,
            'is_hotel_text' => $this->location_type === 'hotel' ? $this->hotel?->name.' Booking' : 'Private Residence',
            'special_considerations' => collect($this->special_considerations ?? [])->join(', ') ?: 'None',
            'notes' => $this->caregiver_notes,
            'notes_to_sitter' => $this->caregiver_notes,
            'notes_for_sitter' => $this->caregiver_notes,
            'notes_to_admin' => $this->notes_to_sitterwise,
            'hourly_rate' => number_format($this->charge_to_client_hourly, 2),
            'admin_booking_url' => route('bookings.index', ['month' => $start->month, 'year' => $start->year]),
            'caregiver_first_name' => $this->caregiver?->first_name ?? 'Sitter',
            'caregiver_name' => $this->caregiver ? $this->caregiver->first_name.' '.$this->caregiver->last_name : 'Sitter',
            'sitter_first_name' => $this->caregiver?->first_name ?? 'Sitter',
            'sitter_name' => $this->caregiver ? $this->caregiver->first_name.' '.$this->caregiver->last_name : 'Sitter',
            'sitter_phone' => $this->caregiver?->phone ?? 'N/A',
            'sitter_profile_url' => $this->caregiver ? route('caregivers.bio', $this->caregiver->slug) : '#',
            'cg_url' => $this->caregiver ? route('caregivers.bio', $this->caregiver->slug) : '#',
            'bio_link' => $this->caregiver ? route('caregivers.bio', $this->caregiver->slug) : '#',
            'service_date' => $start->format('m/d/Y'),
            'review_url' => URL::signedRoute('review.create', ['booking' => $this->ulid]),
            'hotel_fee' => $this->hotel_fee ?? 0.00,
            'reimbursement_amount' => $this->reimbursement ?? 0.00,
            'reimbursement_notes' => $this->reimbursement_description ?? 'N/A',
            'platform_fee' => $this->sitterwise_cut ?? 0.00,
            'total_amount' => $this->total_service_amount ?? 0.00,
            'total_hours' => $this->total_working_hour ?? 0,

        ];
    }

    protected function getPhoneColumns(): array
    {
        return ['client_phone'];
    }
}
