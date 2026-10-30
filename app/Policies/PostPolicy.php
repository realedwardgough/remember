<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Post;
use App\Models\User;
use App\Enum\TimelinePostType;

class PostPolicy
{
    public function view(User $user, Post $post): bool
    {
        return Post::query()->whereKey($post->id)->visibleTo($user)->exists();
    }

    public function share(User $user, Post $post): bool
    {
        return $post->post_type !== TimelinePostType::LETTER && $this->view($user, $post);
    }

    public function update(User $user, Post $post): bool
    {
        return $user->id === $post->author_id;
    }

    public function delete(User $user, Post $post): bool
    {
        return $user->id === $post->author_id;
    }
}
