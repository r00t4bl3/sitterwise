<?php

namespace App\Models\Traits;

/**
 * These accessors delegate to bookingGroup for in-memory reads.
 *
 * NOTE: isDirty() checks Booking's own attributes array, NOT the group's.
 * To detect group-level changes, use $booking->bookingGroup->isDirty('children').
 * The BookingGroupObserver handles auto-repricing when group fields change.
 */
trait HasGroupFields
{
    public function getServiceTypeAttribute(): ?string
    {
        return $this->bookingGroup?->service_type;
    }

    public function getLocationTypeAttribute(): ?string
    {
        return $this->bookingGroup?->location_type;
    }

    public function getChildrenAttribute(): array
    {
        return $this->bookingGroup?->children ?? [];
    }

    public function getPetsAttribute(): array
    {
        return $this->bookingGroup?->pets ?? [];
    }

    public function getSitterPreferencesAttribute(): array
    {
        return $this->bookingGroup?->sitter_preferences ?? [];
    }

    public function getOtherAdultsPresentAttribute(): ?string
    {
        return $this->bookingGroup?->other_adults_present;
    }

    public function getSpecialConsiderationsAttribute(): array
    {
        return $this->bookingGroup?->special_considerations ?? [];
    }

    public function getClientFirstNameAttribute(): ?string
    {
        return $this->bookingGroup?->client_first_name;
    }

    public function getClientLastNameAttribute(): ?string
    {
        return $this->bookingGroup?->client_last_name;
    }

    public function getClientPhoneAttribute(): ?string
    {
        return $this->bookingGroup?->client_phone;
    }

    public function getClientEmailAttribute(): ?string
    {
        return $this->bookingGroup?->client_email;
    }

    public function getAddressLine1Attribute(): ?string
    {
        return $this->bookingGroup?->address_line1;
    }

    public function getAddressLine2Attribute(): ?string
    {
        return $this->bookingGroup?->address_line2;
    }

    public function getAddressCityAttribute(): ?string
    {
        return $this->bookingGroup?->address_city;
    }

    public function getAddressStateAttribute(): ?string
    {
        return $this->bookingGroup?->address_state;
    }

    public function getAddressZipAttribute(): ?string
    {
        return $this->bookingGroup?->address_zip;
    }

    public function getHotelNameAttribute(): ?string
    {
        return $this->bookingGroup?->hotel_name;
    }

    public function getCaregiverNotesAttribute(): ?string
    {
        return $this->bookingGroup?->caregiver_notes;
    }

    public function getNotesToSitterwiseAttribute(): ?string
    {
        return $this->bookingGroup?->notes_to_sitterwise;
    }

    public function getAdminNotesAttribute(): ?string
    {
        return $this->bookingGroup?->admin_notes;
    }

    public function getCorporateIdAttribute(): ?string
    {
        return $this->bookingGroup?->corporate_id;
    }

    public function getChildrenNotesAttribute(): ?string
    {
        return $this->bookingGroup?->children_notes;
    }

    public function getRequiresPaymentAttribute(): bool
    {
        return $this->bookingGroup?->requires_payment ?? true;
    }

    public function getHotelIdAttribute(): ?int
    {
        return $this->bookingGroup?->hotel_id;
    }

    public function getAddressIdAttribute(): ?int
    {
        return $this->bookingGroup?->address_id;
    }

    public function getRentalPlatformAttribute(): ?string
    {
        return $this->bookingGroup?->rental_platform;
    }
}
