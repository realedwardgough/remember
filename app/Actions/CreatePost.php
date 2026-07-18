<?php

namespace App\Actions;

use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Format;
use Intervention\Image\Laravel\Facades\Image;

class CreatePost
{
    private const HashtagPattern = '/(?<![\pL\pN_])#([\pL\pN_][\pL\pN_-]{0,49})/u';

    /**
     * Create a timeline post and attach any uploaded media files.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, UploadedFile>  $mediaFiles
     */
    public function handle(User $author, array $attributes, array $mediaFiles = []): Post
    {
        return DB::transaction(function () use ($author, $attributes, $mediaFiles): Post {
            $content = $attributes['content'] ?? null;
            $tagNames = $this->tagNamesFromContent($content);

            $post = Post::create([
                'author_id' => $author->id,
                'title' => $attributes['title'],
                'content' => $this->contentWithoutHashtags($content),
                'post_type' => $attributes['post_type'],
                'published_at' => $attributes['published_at'],
                'visibility' => Arr::get($attributes, 'visibility', 'family'),
            ]);

            $this->attachTags($post, $tagNames);

            $disk = $this->mediaDisk();

            foreach ($mediaFiles as $sortOrder => $file) {
                $media = $this->storeMediaFile($file, $post, $disk);

                $post->media()->create([
                    'disk' => $disk,
                    'path' => $media['path'],
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $media['mime_type'],
                    'size' => $media['size'],
                    'width' => $media['width'],
                    'height' => $media['height'],
                    'metadata' => $media['metadata'],
                    'sort_order' => $sortOrder,
                ]);
            }

            return $post->load(['author', 'media', 'tags']);
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
    private function attachTags(Post $post, array $tagNames): void
    {
        if ($tagNames === []) {
            return;
        }

        $tagIds = collect($tagNames)
            ->map(fn (string $name): int => Tag::query()->firstOrCreate(['name' => $name])->id)
            ->all();

        $post->tags()->sync($tagIds);
    }

    private function mediaDisk(): string
    {
        return (string) config('filesystems.media_disk', 'gcs');
    }

    /**
     * @return array{path: string, mime_type: string, size: int, width: int|null, height: int|null, metadata: array<string, mixed>}
     */
    private function storeMediaFile(UploadedFile $file, Post $post, string $disk): array
    {
        if (! $this->isImage($file)) {
            $path = $file->store("posts/{$post->id}", ['disk' => $disk]);

            return [
                'path' => $path,
                'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                'size' => $file->getSize(),
                'width' => null,
                'height' => null,
                'metadata' => [
                    'extension' => $file->extension(),
                ],
            ];
        }

        $image = Image::decodePath($file->getRealPath());
        $encoded = $image->encodeUsingFormat(Format::WEBP, quality: 82);
        $path = "posts/{$post->id}/".Str::uuid().'.webp';

        Storage::disk($disk)->put($path, $encoded->toString());

        return [
            'path' => $path,
            'mime_type' => $encoded->mediaType(),
            'size' => $encoded->size(),
            'width' => $image->width(),
            'height' => $image->height(),
            'metadata' => [
                'extension' => 'webp',
                'original_extension' => $file->extension(),
                'optimized' => true,
                'quality' => 82,
            ],
        ];
    }

    private function isImage(UploadedFile $file): bool
    {
        return str_starts_with($file->getMimeType() ?? '', 'image/');
    }
}
