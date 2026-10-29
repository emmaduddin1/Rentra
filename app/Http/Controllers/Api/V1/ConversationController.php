<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ConversationMessageResource;
use App\Http\Resources\V1\ConversationResource;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ConversationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Conversation::class);

        $user = $request->user();

        $conversations = Conversation::forParticipant($user)
            ->availableInMarketplace()
            ->with(['application', 'latestMessage.sender', 'owner', 'property.primaryImage', 'renter'])
            ->withCount([
                'messages as unread_messages_count' => fn ($query) => $query
                    ->where('sender_id', '!=', $user->id)
                    ->whereNull('read_at'),
            ])
            ->orderByDesc('last_message_at')
            ->latest()
            ->paginate($request->integer('per_page') ?: config('rental.pagination.per_page'));

        return ConversationResource::collection($conversations);
    }

    public function show(Request $request, Conversation $conversation): ConversationResource
    {
        Gate::authorize('view', $conversation);

        $conversation->load([
            'application',
            'messages.sender',
            'owner',
            'property.primaryImage',
            'renter',
        ]);

        $conversation->messages()
            ->where('sender_id', '!=', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return new ConversationResource($conversation);
    }

    public function store(Request $request, Conversation $conversation): ConversationMessageResource
    {
        Gate::authorize('createMessage', $conversation);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $message = DB::transaction(function () use ($conversation, $request, $validated) {
            $message = $conversation->messages()->create([
                'sender_id' => $request->user()->id,
                'body' => $validated['body'],
            ]);

            $conversation->forceFill(['last_message_at' => $message->created_at])->save();

            return $message;
        });

        return new ConversationMessageResource($message->load('sender'));
    }
}
