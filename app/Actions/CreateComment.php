<?php

declare(strict_types=1);

namespace App\Actions;

use App\DTOs\CreateCommentDTO;
use App\Models\Comment;
use App\Models\ActivityNotification;
use App\Models\Post;
use App\Services\Messaging\ActivityPush;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class CreateComment
{
    /** @throws AuthorizationException */
    public function handle(Post $post, CreateCommentDTO $data): Comment
    {
        if (! $post->allowsInteractions()) {
            throw new AuthorizationException('Private letters cannot receive comments.');
        }

        return DB::transaction(function () use ($post, $data): Comment {
            $comment = $post->comments()->create($data->toArray());

            if ($post->author_id !== null && $post->author_id !== $data->authorId) {
                $activity = ActivityNotification::query()->create([
                    'user_id' => $post->author_id,
                    'comment_id' => $comment->id,
                ]);
                app(ActivityPush::class)->prepare($activity);
            }

            return $comment;
        });
    }
}
