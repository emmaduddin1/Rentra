<?php

namespace App\Http\Resources\V1;

use App\Models\PropertyVideo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PropertyVideo
 */
class PropertyVideoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // Listing videos are public assets (SRS 9.2), like the gallery.
            'url' => $this->url(),
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'size_mb' => $this->sizeInMegabytes(),
            'sort_order' => $this->sort_order,
        ];
    }
}
