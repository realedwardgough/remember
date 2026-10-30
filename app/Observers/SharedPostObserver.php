<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\MessagePostShare;
use App\Models\Post;
use App\Services\Messaging\MessagingUpdates;

class SharedPostObserver
{
    public function saved(Post $post): void
    {
        $this->notify($post);
    }

    public function deleted(Post $post): void
    {
        $this->notify($post);
    }

    private function notify(Post $post): void
    {
        // original_post_id still exists when a hard deletion has nulled the foreign key.
        MessagePostShare::query()->where('original_post_id', $post->id)->with('message:id,conversation_id')->chunkById(100, function ($shares): void {
            foreach ($shares as $share) {
                if ($share->message !== null) {
                    app(MessagingUpdates::class)->dispatch($share->message->conversation_id, $share->message_id);
                }
            }
        });
    }
}
