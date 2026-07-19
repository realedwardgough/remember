<?php

declare(strict_types=1);

namespace App\Actions;

use App\DTOs\PostData;
use App\Models\Post;
use App\Models\User;
use App\Services\Posts\HashtagService;
use App\Services\Posts\MediaStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

readonly class CreatePost
{
    public function __construct(
        private HashtagService      $hashtags,
        private MediaStorageService $mediaStorage,
    ) {
    }

    /** @param array<int, UploadedFile> $mediaFiles
     * @throws \Throwable
     */
    public function handle(User $author, PostData $data, array $mediaFiles = []): Post
    {
        return DB::transaction(function () use ($author, $data, $mediaFiles): Post {
            $tagNames = $this->hashtags->namesFrom($data->content);
            $post = Post::query()->create([
                'author_id' => $author->id,
                'title' => $data->title,
                'content' => $this->hashtags->removeFrom($data->content),
                'post_type' => $data->postType,
                'published_at' => $data->publishedAt,
                'visibility' => $data->visibility ?? 'family',
            ]);

            $this->hashtags->sync($post, $tagNames);

            foreach ($mediaFiles as $sortOrder => $file) {
                $stored = $this->mediaStorage->store($file, $post);
                $post->media()->create([
                    'disk' => $this->mediaStorage->disk(),
                    'path' => $stored->path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $stored->mimeType,
                    'size' => $stored->size,
                    'width' => $stored->width,
                    'height' => $stored->height,
                    'metadata' => $stored->metadata,
                    'sort_order' => $sortOrder,
                ]);
            }

            return $post->load(['author', 'media', 'tags']);
        });
    }
}
