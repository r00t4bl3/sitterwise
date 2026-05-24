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
            'admin_rating' => $this->admin_rating,
            'biography' => $this->biography,
            'notes' => $this->notes,
            'status' => $this->status ? [
                'value' => $this->status->value,
                'label' => $this->status->label(),
                'color' => $this->status->color(),
            ] : null,
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
                'file_path' => $c->pivot->file_path,
                'notes' => $c->pivot->notes,
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
                'degree' => $e->degree,
            ]),
            'stripe_account_id' => $this->stripe_account_id,
            'stripe_charges_enabled' => $this->stripe_charges_enabled,
            'applications' => $this->whenLoaded('applications', function () {
                return $this->applications->map(fn ($app) => [
                    'id' => $app->id,
                    'submitted_at' => $app->submitted_at?->format('Y-m-d H:i:s'),
                    'data' => $app->data,
                ]);
            }),
            'agreements' => $this->whenLoaded('agreements', function () {
                return $this->agreements->map(fn ($agreement) => [
                    'id' => $agreement->id,
                    'type' => $agreement->type,
                    'pdf_path' => $agreement->pdf_path,
                    'signed_at' => $agreement->signed_at?->format('Y-m-d H:i:s'),
                ]);
            }),
            'reference_requests' => $this->whenLoaded('referenceRequests', function () {
                return $this->referenceRequests->map(fn ($ref) => [
                    'id' => $ref->id,
                    'token' => $ref->token,
                    'reference_name' => $ref->reference_name,
                    'reference_email' => $ref->reference_email,
                    'relationship' => $ref->relationship,
                    'years_known' => $ref->years_known,
                    'is_sponsor' => $ref->is_sponsor,
                    'rating_reliability' => $ref->rating_reliability,
                    'rating_trustworthiness' => $ref->rating_trustworthiness,
                    'rating_maturity' => $ref->rating_maturity,
                    'rating_communication' => $ref->rating_communication,
                    'rating_warmth' => $ref->rating_warmth,
                    'rating_overall_recommendation' => $ref->rating_overall_recommendation,
                    'strengths' => $ref->strengths,
                    'concerns' => $ref->concerns,
                    'additional_comments' => $ref->additional_comments,
                    'submitted_at' => $ref->submitted_at?->format('Y-m-d H:i:s'),
                    'created_at' => $ref->created_at?->format('Y-m-d H:i:s'),
                ]);
            }),
        ];
    }
}
