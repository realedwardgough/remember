<?php

declare(strict_types=1);

namespace App\Actions;

use App\DTOs\CreateCommentDTO;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Auth\Access\AuthorizationException;

class CreateComment
{
    /** @throws AuthorizationException */
    public function handle(Post $post, CreateCommentDTO $data): Comment
    {
        if (! $post->allowsInteractions()) {
            throw new AuthorizationException('Private letters cannot receive comments.');
        }

        return $post->comments()->create($data->toArray());
    }
}
