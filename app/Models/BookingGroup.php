<?php

namespace App\Models;

use App\Enums\ServiceType;
use App\Enums\SitterPreference;
use App\Enums\SpecialConsideration;
use App\Models\Traits\Phone;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\URL;

class BookingGroup extends Model
{
    use HasFactory, Phone, SoftDeletes;

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (BookingGroup $group) {
            $group->calculateSpecialConsiderations();
        });
    }

    protected $fillable = [
        'client_id',
        'submitted_at',
        'submission_type',
        'service_type',
        'location_type',
        'rental_platform',
        'client_first_name',
        'client_last_name',
        'client_phone',
        'client_email',
        'address_id',
        'address_line1',
        'address_line2',
        'address_city',
        'address_state',
        'address_zip',
        'hotel_id',
        'hotel_name',
        'children',
        'pets',
        'children_notes',
        'sitter_preferences',
        'other_adults_present',
        'special_needs_notes',
        'emergency_instructions',
        'how_did_you_hear',
        'caregiver_notes',
        'notes_to_sitterwise',
        'admin_notes',
        'corporate_id',
        'requires_payment',
        'special_considerations',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'children' => 'array',
        'pets' => 'array',
        'sitter_preferences' => 'array',
        'special_considerations' => 'array',
        'requires_payment' => 'boolean',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(ClientAddress::class, 'address_id');
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

    public function toEmailData(): array
    {
        $childrenCount = count($this->children ?? []);
        $childrenSummary = collect($this->children ?? [])
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

        $clientName = trim($this->client_first_name.' '.$this->client_last_name);
        $serviceLabel = ServiceType::tryFrom($this->service_type)?->label() ?? $this->service_type;

        return [
            'client_first_name' => $this->client_first_name,
            'client_name' => $clientName,
            'client_email' => $this->client_email,
            'client_phone' => $this->client_phone,
            'service_requested' => $serviceLabel,
            'service_name' => $serviceLabel,
            'dates' => $this->bookings->map(fn ($booking) => [
                'date' => $booking->start_datetime->setTimezone('America/Los_Angeles')->format('l, F j, Y'),
                'start_time' => $booking->start_datetime->setTimezone('America/Los_Angeles')->format('g:i A'),
                'end_time' => $booking->end_datetime->setTimezone('America/Los_Angeles')->format('g:i A'),
            ])->toArray(),
            'is_multi_day' => true,
            'kids_count' => $childrenCount.' '.($childrenCount !== 1 ? 'children' : 'child'),
            'children_summary' => $childrenSummary ?: 'None',
            'location' => $this->location_type === 'hotel'
                ? ($this->hotel_name ?: $this->hotel?->name ?? 'Hotel').' - '.$this->address_line1
                : $this->address_line1,
            'address' => trim($this->address_line1.' '.$this->address_line2.', '.$this->address_city.', '.$this->address_state.' '.$this->address_zip),
            'hotel_name' => $this->hotel_name ?? $this->hotel?->name ?? 'N/A',
            'service_hotel' => $this->hotel_name ?? $this->hotel?->name ?? 'N/A',
            'is_hotel' => $this->location_type === 'hotel' ? ($this->hotel_name ?? $this->hotel?->name) : false,
            'is_hotel_text' => $this->location_type === 'hotel' ? ($this->hotel_name ?? $this->hotel?->name).' Booking' : 'Private Residence',
            'special_considerations' => collect($this->special_considerations ?? [])
                ->map(fn ($v) => SpecialConsideration::tryFrom($v)?->label() ?? $v)
                ->join(', ') ?: 'None',
            'notes' => $this->caregiver_notes,
            'notes_to_sitter' => $this->caregiver_notes,
            'notes_for_sitter' => $this->caregiver_notes,
            'notes_to_admin' => $this->notes_to_sitterwise,
            'sibling_dates' => $this->bookings->map(fn ($booking) => [
                'ulid' => $booking->ulid,
                'date' => $booking->start_datetime->setTimezone('America/Los_Angeles')->format('l, F j, Y'),
                'start_time' => $booking->start_datetime->setTimezone('America/Los_Angeles')->format('g:i A'),
                'end_time' => $booking->end_datetime->setTimezone('America/Los_Angeles')->format('g:i A'),
                'status' => $booking->status,
                'caregiver_name' => $booking->caregiver?->first_name.' '.$booking->caregiver?->last_name,
                'review_url' => $booking->status === 'completed' || $booking->status === 'paid'
                    ? URL::temporarySignedRoute('review.create', now()->addDays(14), ['booking' => $booking->ulid])
                    : null,
            ])->toArray(),
        ];
    }

    protected function getPhoneColumns(): array
    {
        return ['client_phone'];
    }
}
