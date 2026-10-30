<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\Messaging\SharedPostPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SharedPostController extends Controller
{
    public function __invoke(Request $request, Conversation $conversation, Message $message, SharedPostPresenter $posts): RedirectResponse
    {
        Gate::authorize('view', $conversation);
        abort_unless($message->conversation_id === $conversation->id, 404);
        $message->load(['sharedPost.post', 'conversation.firstParticipant.user', 'conversation.secondParticipant.user']);
        $shared = $posts->forMessage($message, $request->user());
        abort_unless(($shared['status'] ?? null) === 'available', 404);

        return redirect()->route('posts.show', $shared['id']);
    }
}
