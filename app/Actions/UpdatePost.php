<?php

declare(strict_types=1);

namespace App\Actions;

use App\DTOs\PostData;
use App\Models\Post;
use App\Services\Posts\HashtagService;
use Illuminate\Support\Facades\DB;
use Throwable;

readonly class UpdatePost
{
    public function __construct(private HashtagService $hashtags)
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(Post $post, PostData $data): Post
    {
        return DB::transaction(function () use ($post, $data): Post {
            $post->update([
                'title' => $data->title,
                'content' => $this->hashtags->removeFrom($data->content),
                'post_type' => $data->postType,
                'published_at' => $data->publishedAt,
                'visibility' => $data->visibility ?? $post->visibility,
            ]);
            $this->hashtags->sync($post, $this->hashtags->namesFrom($data->content));

            return $post->refresh()->load(['author', 'media', 'tags']);
        });
    }
}
