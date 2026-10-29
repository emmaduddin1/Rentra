<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\ConversationMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ConversationController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Conversation::class);

        $user = $request->user();

        $conversations = Conversation::forParticipant($user)
            ->availableInMarketplace()
            ->with([
                'application',
                'latestMessage.sender',
                'owner',
                'property.primaryImage',
                'renter',
            ])
            ->withCount([
                'messages as unread_messages_count' => fn ($query) => $query
                    ->where('sender_id', '!=', $user->id)
                    ->whereNull('read_at'),
            ])
            ->orderByDesc('last_message_at')
            ->latest()
            ->paginate(config('rental.pagination.per_page'));

        return view('conversations.index', [
            'conversations' => $conversations,
        ]);
    }

    public function show(Request $request, Conversation $conversation): View
    {
        Gate::authorize('view', $conversation);

        $conversation->load(['application', 'owner', 'property.primaryImage', 'renter']);

        $messages = $conversation->messages()
            ->with('sender')
            ->oldest()
            ->paginate(50);

        $conversation->messages()
            ->where('sender_id', '!=', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return view('conversations.show', [
            'conversation' => $conversation,
            'messages' => $messages,
        ]);
    }

    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);

        $validated = $request->validate([
            'after_id' => ['nullable', 'integer', 'min:0'],
        ]);

        $messages = $conversation->messages()
            ->with('sender')
            ->when($validated['after_id'] ?? null, fn ($query, $id) => $query->where('id', '>', $id))
            ->oldest()
            ->limit(50)
            ->get();

        $conversation->messages()
            ->whereIn('id', $messages->pluck('id'))
            ->where('sender_id', '!=', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $conversation->refresh()->load('application');

        return response()->json([
            'messages' => $messages
                ->map(fn (ConversationMessage $message) => $this->serializeMessage($message, $request))
                ->values(),
            'can_message' => $conversation->isWritableBy($request->user()),
            'is_locked' => $conversation->is_locked,
        ]);
    }

    public function store(Request $request, Conversation $conversation): RedirectResponse|JsonResponse
    {
        Gate::authorize('createMessage', $conversation);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $message = DB::transaction(function () use ($conversation, $request, $validated) {
            $recentDuplicate = $conversation->messages()
                ->where('sender_id', $request->user()->id)
                ->where('body', $validated['body'])
                ->where('created_at', '>=', now()->subSeconds(5))
                ->latest()
                ->lockForUpdate()
                ->first();

            if ($recentDuplicate) {
                return $recentDuplicate;
            }

            $message = $conversation->messages()->create([
                'sender_id' => $request->user()->id,
                'body' => $validated['body'],
            ]);

            $conversation->forceFill(['last_message_at' => $message->created_at])->save();

            return $message;
        });

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $this->serializeMessage($message->load('sender'), $request),
            ], 201);
        }

        return redirect()
            ->route('conversations.show', $conversation)
            ->with('status', __('Message sent.'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeMessage(ConversationMessage $message, Request $request): array
    {
        return [
            'id' => $message->id,
            'sender_id' => $message->sender_id,
            'sender_name' => $message->sender?->name ?? __('Someone'),
            'body' => $message->body,
            'mine' => $message->sender_id === $request->user()->id,
            'created_at' => $message->created_at?->toIso8601String(),
            'created_at_label' => $message->created_at?->format('d M Y, H:i'),
        ];
    }
}
