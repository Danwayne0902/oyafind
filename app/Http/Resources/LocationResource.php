<?php

namespace App\Http\Resources;

use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public projection for a reviewed coarse location.
 *
 * @mixin Location
 */
class LocationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $parent = $this->parent;
        $publicParent = $parent && self::isSelectable($parent)
            ? [
                'id' => $parent->id,
                'name' => $parent->name,
                'slug' => $parent->slug,
                'location_type' => $parent->location_type,
            ]
            : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'location_type' => $this->location_type,
            'parent' => $publicParent,
        ];
    }

    private static function isSelectable(Location $location): bool
    {
        return $location->is_active
            && $location->reviewed_at !== null
            && $location->reviewed_by !== null
            && filled($location->catalogue_origin);
    }
}
