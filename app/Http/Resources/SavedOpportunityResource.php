<?php

namespace App\Http\Resources;

use App\Models\SavedOpportunity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SavedOpportunity
 */
class SavedOpportunityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isAvailable = (bool) ($this->resource->is_available ?? false);

        $opportunityData = null;
        if ($this->opportunity) {
            if ($isAvailable) {
                $opportunityData = OpportunityResource::make($this->opportunity)->resolve();
            } else {
                $opportunityData = [
                    'id' => $this->opportunity->id,
                    'title' => $this->opportunity->title ?? 'Opportunity no longer available',
                    'slug' => $this->opportunity->slug ?? null,
                    'is_available' => false,
                ];
            }
        }

        return [
            'id' => $this->id,
            'opportunity_id' => $this->opportunity_id,
            'is_available' => $isAvailable,
            'created_at' => $this->created_at?->toIso8601String(),
            'opportunity' => $opportunityData,
        ];
    }
}

