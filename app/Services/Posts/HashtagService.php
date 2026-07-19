<?php

declare(strict_types=1);

namespace App\Services\Posts;

use App\Models\Post;
use App\Models\Tag;
use Illuminate\Support\Str;

class HashtagService
{
    private const string Pattern = '/(?<![\pL\pN_])#([\pL\pN_][\pL\pN_-]{0,49})/u';

    /** @return list<string> */
    public function namesFrom(?string $content): array
    {
        if ($content === null || $content === '') {
            return [];
        }

        preg_match_all(self::Pattern, $content, $matches);

        return collect($matches[1] ?? [])->map(
            static fn (string $tag): string => Str::lower($tag),
        )->unique()->values()->all();
    }

    public function removeFrom(?string $content): ?string
    {
        if ($content === null || $content === '') {
            return null;
        }

        $content = preg_replace(self::Pattern, '', $content) ?? $content;
        $content = preg_replace('/[ \t]{2,}/', ' ', $content) ?? $content;
        $content = preg_replace('/ *([.,!?;:])/', '$1', $content) ?? $content;
        $content = trim($content);

        return $content === '' ? null : $content;
    }

    /** @param list<string> $tagNames */
    public function sync(Post $post, array $tagNames): void
    {
        $tagIds = collect($tagNames)->map(
            static fn (string $name): int => Tag::query()->firstOrCreate(['name' => $name])->id,
        )->all();

        $post->tags()->sync($tagIds);
    }
}
