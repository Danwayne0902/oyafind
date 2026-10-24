<?php

namespace App\Http\Resources;

use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpportunityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $location = $this->relationLoaded('location') ? $this->location : null;
        $locationIsPublic = config('oyafind.policies.location_catalogue') === true
            && $location instanceof Location
            && $location->is_active
            && $location->reviewed_at !== null
            && $location->reviewed_by !== null
            && filled($location->catalogue_origin);

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'summary' => $this->summary,
            'description' => $this->description,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ]),
            'location' => $locationIsPublic
                ? LocationResource::make($location)->resolve()
                : null,
            'organisation_name' => $this->organisation_name,
            'source_url' => $this->source_url,
            'application_url' => $this->application_url,
            'application_method' => $this->application_method,
            'application_instructions' => $this->application_instructions,
            'opportunity_type' => $this->opportunity_type,
            'work_mode' => $this->work_mode,
            'eligibility' => $this->eligibility,
            'requirements' => $this->requirements,
            'amount_min' => $this->amount_min,
            'amount_max' => $this->amount_max,
            'currency' => $this->currency,
            'deadline_at' => $this->deadline_at?->utc()->toIso8601String(),
            'publication_status' => 'published',
            'verification_status' => 'verified',
            'published_at' => $this->published_at?->utc()->toIso8601String(),
        ];
    }
}
