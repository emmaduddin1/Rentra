<?php

namespace App\Http\Resources\V1;

use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Conversation
 */
class ConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'property_id' => $this->property_id,
            'application_id' => $this->application_id,
            'renter_id' => $this->renter_id,
            'owner_id' => $this->owner_id,
            'is_locked' => $this->is_locked,
            'can_message' => $user ? $this->isWritableBy($user) : false,
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'locked_at' => $this->locked_at?->toIso8601String(),
            'unread_messages_count' => $this->unread_messages_count ?? 0,
            'property' => new ListingResource($this->whenLoaded('property')),
            'application' => $this->whenLoaded('application', fn () => [
                'id' => $this->application->id,
                'status' => $this->application->status->value,
                'status_label' => $this->application->status->label(),
                'can_message' => $this->application->canMessage(),
            ]),
            'renter' => new UserResource($this->whenLoaded('renter')),
            'owner' => new UserResource($this->whenLoaded('owner')),
            'latest_message' => new ConversationMessageResource($this->whenLoaded('latestMessage')),
            'messages' => ConversationMessageResource::collection($this->whenLoaded('messages')),
        ];
    }
}
