<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Post;
use App\Models\Tag;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UpdatePost
{
    private const HashtagPattern = '/(?<![\pL\pN_])#([\pL\pN_][\pL\pN_-]{0,49})/u';

    /**
     * Update a timeline post and sync any hashtags from the new content.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Post $post, array $attributes): Post
    {
        return DB::transaction(function () use ($post, $attributes): Post {
            $content = $attributes['content'] ?? null;
            $tagNames = $this->tagNamesFromContent($content);

            $post->update([
                'title' => $attributes['title'],
                'content' => $this->contentWithoutHashtags($content),
                'post_type' => $attributes['post_type'],
                'published_at' => $attributes['published_at'],
                'visibility' => Arr::get($attributes, 'visibility', $post->visibility),
            ]);

            $this->syncTags($post, $tagNames);

            return $post->refresh()->load(['author', 'media', 'tags']);
        });
    }

    /**
     * @return array<int, string>
     */
    private function tagNamesFromContent(?string $content): array
    {
        if ($content === null || $content === '') {
            return [];
        }

        preg_match_all(self::HashtagPattern, $content, $matches);

        return collect($matches[1] ?? [])
            ->map(fn (string $tag): string => Str::lower($tag))
            ->unique()
            ->values()
            ->all();
    }

    private function contentWithoutHashtags(?string $content): ?string
    {
        if ($content === null || $content === '') {
            return null;
        }

        $content = preg_replace(self::HashtagPattern, '', $content) ?? $content;
        $content = preg_replace('/[ \t]{2,}/', ' ', $content) ?? $content;
        $content = preg_replace('/ *([.,!?;:])/', '$1', $content) ?? $content;
        $content = trim($content);

        return $content === '' ? null : $content;
    }

    /**
     * @param  array<int, string>  $tagNames
     */
    private function syncTags(Post $post, array $tagNames): void
    {
        $tagIds = collect($tagNames)
            ->map(fn (string $name): int => Tag::query()->firstOrCreate(['name' => $name])->id)
            ->all();

        $post->tags()->sync($tagIds);
    }
}
