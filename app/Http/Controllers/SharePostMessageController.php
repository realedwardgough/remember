<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Messaging\SharePostMessage;
use App\Actions\Messaging\SendMessage;
use App\Models\Conversation;
use App\Http\Requests\Messaging\SharePostRequest;
use App\Http\Resources\Messaging\MessageResource;
use Illuminate\Http\JsonResponse;

class SharePostMessageController extends Controller
{
    public function __invoke(SharePostRequest $request, string $postId, SharePostMessage $share, SendMessage $send): JsonResponse
    {
        $id = filter_var($postId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        abort_if($id === false, 404);
        $message = $request->has('conversation_id')
            ? $send->handle($request->user(), Conversation::query()->findOrFail($request->integer('conversation_id')), $request->validated('body') ?? '', $request->validated('client_request_id'), postId: $id)
            : $share->handle($request->user(), $request->integer('recipient_id'), $id, $request->validated('body') ?? '', $request->validated('client_request_id'));
        $message->load(['sender.user', 'attachments', 'sharedPost.post', 'conversation.participants.user']);

        return (new MessageResource($message))->response()->setStatusCode($message->wasRecentlyCreated ? 201 : 200);
    }
}
