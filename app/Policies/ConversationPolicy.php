<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner() || $user->isRenter();
    }

    public function view(User $user, Conversation $conversation): bool
    {
        return $conversation->isParticipant($user);
    }

    public function createMessage(User $user, Conversation $conversation): bool
    {
        return $conversation->isWritableBy($user);
    }
}
