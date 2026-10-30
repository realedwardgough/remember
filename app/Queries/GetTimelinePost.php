<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class GetTimelinePost
{
    public function handle(User $viewer, int $postId): Post
    {
        return Post::query()->visibleTo($viewer)->whereKey($postId)
            ->with([
                'author', 'media', 'tags',
                'comments' => fn ($query) => $query->with('author')->withCount('hearts')->withExists([
                    'hearts as hearted_by_viewer' => fn (Builder $query) => $query->where('user_id', $viewer->id),
                ]),
            ])->withCount('hearts')
            ->withExists(['hearts as hearted_by_viewer' => fn (Builder $query) => $query->where('user_id', $viewer->id)])
            ->firstOrFail();
    }
}
