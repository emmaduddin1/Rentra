<?php

namespace App\Http\Resources\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * SRS NFR-MNT-004 - a stable, versioned shape for the Flutter clients.
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'status' => $this->status->value,
            'product_tour_completed_at' => $this->when($request->user()?->id === $this->id, fn () => $this->product_tour_completed_at?->toIso8601String()),
            'profile_image_url' => $this->profile_image
                ? Storage::disk(config('rental.uploads.images.disk'))->url($this->profile_image)
                : null,
        ];
    }
}
