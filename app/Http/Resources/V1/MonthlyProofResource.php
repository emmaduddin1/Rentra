<?php

namespace App\Http\Resources\V1;

use App\Models\MonthlyProof;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * NFR-SEC-007 - only the authorised download route is exposed, never a path on
 * the private disk.
 *
 * @mixin MonthlyProof
 */
class MonthlyProofResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category->value,
            'category_label' => $this->category->label(),
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'is_image' => $this->isImage(),
            'download_url' => route('monthly-proofs.download', $this->resource),
        ];
    }
}
