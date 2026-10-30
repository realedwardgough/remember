<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Messaging\MarkConversationRead;
use App\Actions\Messaging\SendMessage;
use App\Actions\Messaging\StartConversation;
use App\Http\Requests\Messaging\ReadConversationRequest;
use App\Http\Requests\Messaging\MessagingPageRequest;
use App\Http\Requests\Messaging\SendMessageRequest;
use App\Http\Requests\Messaging\StartConversationRequest;
use App\Http\Resources\Messaging\ConversationResource;
use App\Http\Resources\Messaging\MessageResource;
use App\Models\Conversation;
use App\Models\MessagingParticipant;
use App\Models\User;
use App\Queries\GetUnreadMessageCount;
use App\Services\Messaging\ReadReceiptToken;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class MessagingController extends Controller
{
    public function contacts(MessagingPageRequest $request): JsonResponse
    {
        $contacts = User::query()->where('id', '!=', $request->user()->id)
            ->select(['id', 'name', 'username'])->orderBy('id')->cursorPaginate(50);

        return response()->json([
            'data' => $contacts->items(),
            'next_cursor' => $contacts->nextCursor()?->encode(),
        ]);
    }

    public function index(MessagingPageRequest $request, GetUnreadMessageCount $unread): JsonResponse
    {
        $participantId = MessagingParticipant::query()->where('user_id', $request->user()->id)->value('id');
        $conversations = Conversation::query()->forUser($request->user())
            ->with(['firstParticipant.user', 'secondParticipant.user', 'latestMessage.sender.user'])
            ->withCount(['messages as unread_count' => function (Builder $messages) use ($participantId): void {
                $messages->where('sender_participant_id', '!=', $participantId)
                    ->whereRaw('messages.id > COALESCE((SELECT last_read_message_id FROM conversation_memberships WHERE conversation_memberships.conversation_id = conversations.id AND participant_id = ?), 0)', [$participantId]);
            }])
            ->orderByDesc('updated_at')->orderByDesc('id')->cursorPaginate(25);

        return response()->json([
            'data' => ConversationResource::collection($conversations->getCollection()),
            'next_cursor' => $conversations->nextCursor()?->encode(),
            'unread_count' => $unread->handle($request->user()),
        ]);
    }

    public function store(StartConversationRequest $request, StartConversation $start): JsonResponse
    {
        $conversation = $start->handle($request->user(), $request->integer('recipient_id'));
        $conversation->load(['firstParticipant.user', 'secondParticipant.user', 'latestMessage.sender.user']);

        return (new ConversationResource($conversation))->response()->setStatusCode($conversation->wasRecentlyCreated ? 201 : 200);
    }

    public function show(Request $request, Conversation $conversation, ReadReceiptToken $tokens): JsonResponse
    {
        Gate::authorize('view', $conversation);
        $request->validate([
            'before_id' => ['nullable', 'integer', 'min:1'],
            'after_id' => ['nullable', 'integer', 'min:1', 'prohibits:before_id'],
        ]);

        $after = $request->integer('after_id');
        $before = $request->integer('before_id');
        $query = $conversation->messages()->with('sender.user')
            ->when($before, fn ($query) => $query->where('id', '<', $before))
            ->when($after, fn ($query) => $query->where('id', '>', $after));
        $messages = ($after ? $query->orderBy('id') : $query->orderByDesc('id'))->limit(51)->get();
        $hasMore = $messages->count() > 50;
        $messages = $messages->take(50)->sortBy('id')->values();
        $conversation->load(['firstParticipant.user', 'secondParticipant.user']);
        $lastId = $messages->last()?->id;

        // Return a receipt only for a message actually included in this response.
        return response()->json([
            'conversation' => new ConversationResource($conversation),
            'data' => MessageResource::collection($messages),
            'has_more' => $hasMore,
            'next_before_id' => ! $after && $hasMore ? $messages->first()->id : null,
            'next_after_id' => $after && $hasMore ? $lastId : null,
            'read_token' => $lastId ? $tokens->issue($request->user(), $conversation, $lastId) : null,
        ]);
    }

    public function send(SendMessageRequest $request, Conversation $conversation, SendMessage $send): JsonResponse
    {
        $message = $send->handle(
            $request->user(),
            $conversation,
            $request->validated('body'),
            $request->validated('client_request_id'),
        );
        $message->load('sender.user');

        return (new MessageResource($message))->response()->setStatusCode($message->wasRecentlyCreated ? 201 : 200);
    }

    public function read(ReadConversationRequest $request, Conversation $conversation, MarkConversationRead $read): Response
    {
        $read->handle($request->user(), $conversation, $request->validated('read_token'));

        return response()->noContent();
    }
}
