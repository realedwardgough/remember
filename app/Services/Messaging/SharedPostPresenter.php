<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Enum\TimelinePostType;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class SharedPostPresenter
{
    public function forMessage(Message $message, User $viewer): ?array
    {
        $share = $message->sharedPost;
        if ($share === null) {
            return null;
        }
        $unavailable = ['status' => 'unavailable', 'label' => 'Post unavailable'];
        $post = $share->post;
        if ($post === null || $post->post_type === TimelinePostType::LETTER || ! Gate::forUser($viewer)->allows('view', $post)) {
            return $unavailable;
        }
        $conversation = $message->conversation;
        foreach ([$conversation->firstParticipant->user, $conversation->secondParticipant->user] as $participant) {
            // Deleted accounts cannot read anything; history remains useful to surviving authorized members.
            if ($participant !== null && ! Gate::forUser($participant)->allows('view', $post)) {
                return $unavailable;
            }
        }

        return [
            'status' => 'available',
            'id' => $post->id,
            'title' => $post->title,
            'type' => $post->post_type->value,
            'date' => $post->published_at->format('j F Y'),
            'url' => route('messages.shared-post', [$message->conversation_id, $message->id]),
        ];
    }
}
