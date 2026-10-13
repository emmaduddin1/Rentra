<?php

namespace App\Actions\Messaging;

use App\Models\Application;
use App\Models\Conversation;

class EnsureApplicationConversation
{
    public function handle(Application $application): ?Conversation
    {
        $application->loadMissing('property');

        if (! $application->canMessage()) {
            $conversation = $application->conversation;

            if ($conversation && ! $conversation->is_locked) {
                $conversation->forceFill([
                    'is_locked' => true,
                    'locked_at' => now(),
                ])->save();
            }

            return $conversation;
        }

        return Conversation::query()->updateOrCreate(
            ['application_id' => $application->id],
            [
                'property_id' => $application->property_id,
                'renter_id' => $application->renter_id,
                'owner_id' => $application->property->owner_id,
                'is_locked' => false,
                'locked_at' => null,
            ],
        );
    }
}
