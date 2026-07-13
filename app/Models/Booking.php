<?php

namespace App\Models;

use App\Enums\AssignmentResolution;
use App\Enums\BookingStatus;
use App\Enums\ServiceType;
use App\Enums\SpecialConsideration;
use App\Models\Traits\HasGroupFields;
use App\Models\Traits\Phone;
use App\Services\CaregiverRecommendation\AvailabilityReservationService;
use App\Support\BusinessTime;
use App\Support\Settings;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class Booking extends Model
{
    use HasFactory, HasGroupFields, Phone, SoftDeletes;

    /**
     * Fallback minimum billable hours used when the `bookings.minimum_hours`
     * setting row is absent (e.g. a pre-seed install). The authoritative value
     * is the super-admin setting.
     */
    private const MINIMUM_BILLABLE_HOURS = 4;

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
            $skip = [BookingStatus::Paid->value, BookingStatus::Cancelled->value];

            if (in_array($booking->status, $skip, true)) {
                return;
            }

            if ($booking->isDirty(['start_datetime', 'end_datetime'])) {
                $booking->calculateTotalWorkingHours();
            }

            $booking->calculateTotalAmount();
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

    public function calculateHourlyRate(?BookingGroup $group = null): void
    {
        $group ??= $this->bookingGroup;

        if (! $group) {
            return;
        }

        $maxChildren = PricingRule::where('service_type', $group->service_type)->max('number_of_children');
        $numberOfChildren = min(count($group->children ?? []), $maxChildren ?? 0);

        $query = PricingRule::where('service_type', $group->service_type)
            ->where('number_of_children', $numberOfChildren);

        if ($group->service_type === 'petsitter') {
            $query->where('is_for_pets', ! empty($group->pets));
        }

        $pricingRule = $query->first();

        if (! $pricingRule) {
            $pricingRule = PricingRule::where('service_type', $group->service_type)->first();
        }

        if ($pricingRule) {
            $this->charge_to_client_hourly = $pricingRule->charge_to_client;
            $this->paid_to_caregiver_hourly = $pricingRule->paid_to_caregiver;
            $this->sitterwise_cut_hourly = $pricingRule->sitterwise_cut;
        } else {
            $this->charge_to_client_hourly = null;
            $this->paid_to_caregiver_hourly = null;
            $this->sitterwise_cut_hourly = null;
        }
    }

    public function calculateTotalAmount(): void
    {
        if ($this->status === BookingStatus::Paid->value) {
            return;
        }

        if ($this->status === BookingStatus::Cancelled->value) {
            $this->charge_to_client = 0;
            $this->paid_to_caregiver = 0;
            $this->sitterwise_cut = 0;
            $this->total_service_amount = 0;
            $this->total_amount = 0;
            $this->paid_to_caregiver_total = 0;

            return;
        }

        if ($this->end_datetime?->isPast() && $this->status !== BookingStatus::Completed->value) {
            return;
        }

        // Bill (and pay) a 4-hour minimum even when the actual worked time is
        // shorter — checkout now allows sub-4h true times, but the engagement is
        // still charged at the 4h floor. total_working_hour keeps the true elapsed
        // time; only the money is floored. No-op for the ≥4h case.
        $minimumHours = (float) Settings::get('bookings.minimum_hours', self::MINIMUM_BILLABLE_HOURS);
        $billableHours = $this->total_working_hour > 0
            ? max((float) $this->total_working_hour, $minimumHours)
            : (float) $this->total_working_hour;

        $this->charge_to_client = round($this->charge_to_client_hourly * $billableHours, 2);
        $this->paid_to_caregiver = round($this->paid_to_caregiver_hourly * $billableHours, 2);
        $this->sitterwise_cut = round($this->sitterwise_cut_hourly * $billableHours, 2);

        $reimbursement = (float) ($this->getAttribute('reimbursement') ?? 0);
        $bonus = (float) ($this->getAttribute('bonus') ?? 0);
        $tip = (float) ($this->getAttribute('tip') ?? 0);

        $this->total_service_amount = round($this->charge_to_client + $reimbursement + $bonus, 2);
        $this->total_amount = round($this->total_service_amount + $tip, 2);
        $this->paid_to_caregiver_total = round($this->paid_to_caregiver + $reimbursement + $bonus + $tip, 2);
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
        'service_type',
        'location_type',
        'children',
        'pets',
        'sitter_preferences',
        'other_adults_present',
        'special_considerations',
        'client_first_name',
        'client_last_name',
        'client_phone',
        'client_email',
        'address_line1',
        'address_line2',
        'address_city',
        'address_state',
        'address_zip',
        'hotel_name',
        'hotel_id',
        'address_id',
        'rental_platform',
        'caregiver_notes',
        'notes_to_sitterwise',
        'admin_notes',
        'corporate_id',
        'children_notes',
        'requires_payment',
        'payment_form',
        'client_id',
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

        if (is_string($value) && (str_ends_with($value, 'Z') || preg_match('/[+-]\d{2}:\d{2}$/', $value))) {
            return Carbon::parse($value)
                ->setTimezone('UTC')
                ->format('Y-m-d H:i:s');
        }

        return Carbon::parse($value, 'America/Los_Angeles')
            ->setTimezone('UTC')
            ->format('Y-m-d H:i:s');
    }

    public function casts(): array
    {
        return [
            'start_datetime' => 'datetime',
            'end_datetime' => 'datetime',
            'reservation_expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'review_reminder_email_sent_at' => 'datetime',
            'review_reminder_sms_sent_at' => 'datetime',
            'last_charge_attempt_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'checkout_at' => 'datetime',
            'lifesaver_override' => 'boolean',
            'total_amount' => 'decimal:2',
            'caregiver_amount' => 'decimal:2',
            'reimbursement' => 'decimal:2',
            'reimbursement_description' => 'string',
            'bonus' => 'decimal:2',
            'tip' => 'decimal:2',
            'actual_amount' => 'decimal:2',
            'charge_attempt_count' => 'integer',
            'charge_to_client_hourly' => 'decimal:2',
            'paid_to_caregiver_hourly' => 'decimal:2',
            'sitterwise_cut_hourly' => 'decimal:2',
            'total_service_amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (Booking $booking) {
            $reservationService = app(AvailabilityReservationService::class);

            $caregiverChanged = $booking->wasRecentlyCreated || $booking->wasChanged('caregiver_id');

            if ($caregiverChanged) {
                if ($booking->getOriginal('caregiver_id')) {
                    $reservationService->release($booking);
                }

                if ($booking->caregiver_id) {
                    $reservationService->reserve($booking);
                }
            }

            if ($booking->wasChanged('status') && $booking->status === 'cancelled' && $booking->caregiver_id) {
                $reservationService->release($booking);
            }

            if (! $booking->wasRecentlyCreated && $booking->caregiver_id && (
                $booking->wasChanged('start_datetime') || $booking->wasChanged('end_datetime')
            )) {
                $reservationService->release($booking);
                $reservationService->reserve($booking);
            }

            if ($caregiverChanged && $booking->caregiver_id) {
                if ($booking->wasChanged('caregiver_id')) {
                    $oldCaregiverId = $booking->getOriginal('caregiver_id');

                    if ($oldCaregiverId) {
                        $oldAssignment = $booking->assignments()
                            ->where('caregiver_id', $oldCaregiverId)
                            ->unresolved()
                            ->first();

                        if ($oldAssignment) {
                            $oldAssignment->resolve(AssignmentResolution::Reassigned, 'Caregiver changed via booking edit');
                        }
                    }
                }

                // updateOrCreate (not firstOrCreate): re-assigning a caregiver who
                // previously had a resolved assignment on this booking must
                // reactivate that row, otherwise the booking has a caregiver but
                // no *unresolved* assignment. The unique (caregiver_id, booking_id)
                // constraint means there is only ever one row per pair to reuse.
                $booking->assignments()->updateOrCreate(
                    ['caregiver_id' => $booking->caregiver_id],
                    [
                        'assigned_at' => now(),
                        'resolution' => null,
                        'resolution_at' => null,
                        'resolution_note' => null,
                    ],
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

    public function getClientIdAttribute(): ?int
    {
        return $this->bookingGroup?->client_id;
    }

    public function client(): HasOneThrough
    {
        return $this->hasOneThrough(
            Client::class,
            BookingGroup::class,
            'id',
            'id',
            'booking_group_id',
            'client_id'
        );
    }

    public function hotel(): HasOneThrough
    {
        return $this->hasOneThrough(
            Hotel::class,
            BookingGroup::class,
            'id',
            'id',
            'booking_group_id',
            'hotel_id'
        );
    }

    public function address(): HasOneThrough
    {
        return $this->hasOneThrough(
            ClientAddress::class,
            BookingGroup::class,
            'id',
            'id',
            'booking_group_id',
            'address_id'
        );
    }

    public function caregiver()
    {
        return $this->belongsTo(Caregiver::class);
    }

    public function availability()
    {
        return $this->belongsTo(Availability::class);
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
        return $query->where('start_datetime', '>', BusinessTime::now()->startOfDay()->utc());
    }

    public function scopeInToday($query)
    {
        $today = BusinessTime::now();

        return $query->whereBetween('start_datetime', [
            $today->copy()->startOfDay()->utc(),
            $today->copy()->endOfDay()->utc(),
        ]);
    }

    public function scopeSearchGroupFields($query, string $search)
    {
        return $query->whereHas('bookingGroup', function ($q) use ($search) {
            $q->where('corporate_id', 'like', "%{$search}%")
                ->orWhere('address_line1', 'like', "%{$search}%")
                ->orWhere('address_city', 'like', "%{$search}%")
                ->orWhere('address_state', 'like', "%{$search}%")
                ->orWhere('address_zip', 'like', "%{$search}%");
        });
    }

    public function getServiceTypeLabelAttribute(): ?string
    {
        $serviceType = $this->bookingGroup?->service_type;

        if (! $serviceType) {
            return null;
        }

        return ServiceType::tryFrom($serviceType)?->label() ?? $serviceType;
    }

    public function toEmailData(): array
    {
        $group = $this->bookingGroup;

        $groupBookings = $group->bookings->sortBy('start_datetime')->values();

        $start = $this->start_datetime instanceof Carbon
            ? $this->start_datetime->copy()->setTimezone('America/Los_Angeles')
            : Carbon::parse($this->start_datetime)->setTimezone('America/Los_Angeles');
        $end = $this->end_datetime instanceof Carbon
            ? $this->end_datetime->copy()->setTimezone('America/Los_Angeles')
            : Carbon::parse($this->end_datetime)->setTimezone('America/Los_Angeles');

        $childrenCount = count($group->children ?? []);
        $childrenSummary = collect($group->children ?? [])
            ->map(function ($c) {
                $name = $c['name'] ?? 'Child';
                $age = null;
                if (isset($c['birth_year'])) {
                    $age = now()->year - (int) $c['birth_year'];
                } elseif (isset($c['birth_date'])) {
                    $age = Carbon::parse($c['birth_date'])->diffInYears(now());
                }

                return $age ? "{$name} ({$age}yr)" : $name;
            })
            ->join(', ');

        $clientName = ($group->client?->first_name ?? $group->client_first_name).' '.($group->client?->last_name ?? $group->client_last_name);

        return [
            'booking_id' => $this->id,
            'job_id' => $this->id,
            'corporate_id' => $group->corporate_id,
            'ulid' => $this->ulid,
            'client_first_name' => $group->client?->first_name ?? $group->client_first_name,
            'client_name' => $clientName,
            'client_email' => $group->client?->user?->email ?? $group->client_email,
            'client_phone' => $group->client?->phone ?? $group->client_phone,
            'service_requested' => $this->service_type_label,
            'service_name' => $this->service_type_label,
            'date' => $start->format('l, F j, Y'),
            'date_times' => $start->format('l, F j, Y'),
            'service_date_pretty' => $start->format('D, M j, Y'),
            'start_time' => $start->format('g:i A'),
            'time' => $start->format('g:i A'),
            'end_time' => $end->format('g:i A'),
            'service_time' => $start->format('g:i A'),
            'service_time_range' => $start->format('g:i A').' - '.$end->format('g:i A'),
            'dates' => $groupBookings->map(fn ($booking) => [
                'date' => $booking->start_datetime->copy()->setTimezone('America/Los_Angeles')->format('l, F j, Y'),
                'start_time' => $booking->start_datetime->copy()->setTimezone('America/Los_Angeles')->format('g:i A'),
                'end_time' => $booking->end_datetime->copy()->setTimezone('America/Los_Angeles')->format('g:i A'),
            ])->toArray(),
            'is_multi_day' => $groupBookings->count() > 1,
            'kids_count' => $childrenCount.' '.Str::plural('child', $childrenCount),
            'children_summary' => $childrenSummary ?: 'None',
            'location' => $group->location_type === 'hotel'
                ? ($group->hotel_name ?: $group->hotel?->name ?? 'Hotel').' - '.trim($group->address_line1.' '.$group->address_line2.', '.$group->address_city.', '.$group->address_state.' '.$group->address_zip)
                : $group->address_line1,
            'address' => trim($group->address_line1.' '.$group->address_line2.', '.$group->address_city.', '.$group->address_state.' '.$group->address_zip),
            'hotel_name' => $group->hotel_name ?? $group->hotel?->name ?? 'N/A',
            'service_hotel' => $group->hotel_name ?? $group->hotel?->name ?? 'N/A',
            'is_hotel' => $group->location_type === 'hotel' ? ($group->hotel_name ?? $group->hotel?->name) : false,
            'is_hotel_text' => $group->location_type === 'hotel' ? ($group->hotel_name ?? $group->hotel?->name).' Booking' : 'Private Residence',
            'special_considerations' => collect($group->special_considerations ?? [])
                ->map(fn ($v) => SpecialConsideration::tryFrom($v)?->label() ?? $v)
                ->join(', ') ?: 'None',
            'notes' => $group->caregiver_notes,
            'notes_to_sitter' => $group->caregiver_notes,
            'notes_for_sitter' => $group->caregiver_notes,
            'notes_to_admin' => $group->notes_to_sitterwise,
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
            'review_url' => URL::temporarySignedRoute('review.create', now()->addDays(14), ['booking' => $this->ulid]),
            'hotel_fee' => $this->hotel_fee ?? 0.00,
            'reimbursement_amount' => $this->reimbursement ?? 0.00,
            'reimbursement_notes' => $this->reimbursement_description ?? 'N/A',
            'platform_fee' => $this->sitterwise_cut ?? 0.00,
            'total_service_amount' => $this->total_service_amount ?? 0.00,
            'total_amount' => $this->total_amount ?? 0.00,
            'total_hours' => $this->total_working_hour ?? 0,
        ];
    }

    protected function getPhoneColumns(): array
    {
        return [];
    }
}
