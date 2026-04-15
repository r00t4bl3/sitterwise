<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CaregiverResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isEdit = $request->routeIs('caregivers.edit');

        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'slug' => $this->slug,
            'email' => $this->user?->email,
            'phone' => $this->phone,
            'address_line1' => $this->address_line1,
            'address_line2' => $this->address_line2,
            'address_city' => $this->address_city,
            'address_state' => $this->address_state,
            'address_zip' => $this->address_zip,
            'date_of_birth' => $isEdit
                ? $this->date_of_birth
                : ($this->date_of_birth
                    ? Carbon::parse($this->date_of_birth)->format('F j, Y')
                    : null),
            'date_of_birth_raw' => $this->date_of_birth,
            'user' => [
                'profile_photo_path' => $this->user->profile_photo_path ?? null,
                'profile_photo_url' => $this->user->profile_photo_url ?? null,
            ],
            'rating' => $this->rating,
            'biography' => $this->biography,
            'notes' => $this->notes,
            'status' => $isEdit ? null : $this->status,
            'status_id' => $this->status_id,
            'specialty_type_ids' => $this->specialtyTypes->pluck('id')->toArray(),
            'specialty_types' => $isEdit ? null : $this->specialtyTypes,
            'location_ids' => $this->locations->pluck('id')->toArray(),
            'preferred_location_id' => $this->locations()->wherePivot('is_preferred', true)->first()?->id,
            'locations' => $this->locations->map(fn ($l) => [
                'id' => $l->id,
                'name' => $l->name,
                'is_preferred' => (bool) $l->pivot->is_preferred,
            ]),
            'certifications' => $this->certifications->map(fn ($c) => [
                'id' => $c->pivot->id ?? $c->id,
                'certification_type_id' => $c->pivot->certification_type_id,
                'certification_type' => [
                    'id' => $c->id,
                    'name' => $c->name,
                ],
                'expiration_date' => $c->pivot->expiration_date,
                'verified_at' => $c->pivot->verified_at,
            ]),
            'attributes' => $this->attributes->map(fn ($a) => [
                'id' => $a->id,
                'attribute_definition' => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'slug' => $a->slug,
                ],
                'value' => $a->pivot->value,
            ]),
            'educations' => $this->educations->map(fn ($e) => [
                'id' => $e->id,
                'education_type' => $e->education_type,
                'school_name' => $e->school_name,
                'graduation_year' => $e->graduation_year,
            ]),
            'stripe_account_id' => $this->stripe_account_id,
            'stripe_charges_enabled' => $this->stripe_charges_enabled,
        ];
    }
}
