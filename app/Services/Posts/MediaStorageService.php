<?php

declare(strict_types=1);

namespace App\Services\Posts;

use App\DTOs\StoredMediaDTO;
use App\Models\Post;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Format;
use Intervention\Image\Laravel\Facades\Image;

class MediaStorageService
{
    public function disk(): string
    {
        return (string) config('filesystems.media_disk', 'gcs');
    }

    public function store(UploadedFile $file, Post $post): StoredMediaDTO
    {
        if (! str_starts_with($file->getMimeType() ?? '', 'image/')) {
            $video = str_starts_with($file->getMimeType() ?? '', 'video/');
            $directory = $video ? "posts/{$post->id}/videos/originals" : "posts/{$post->id}";
            $path = $file->store($directory, ['disk' => $this->disk(), 'visibility' => 'private']);

            return new StoredMediaDTO(
                path: $path,
                mimeType: $file->getMimeType() ?? 'application/octet-stream',
                size: $file->getSize(),
                width: null,
                height: null,
                metadata: array_filter(['extension' => $file->extension(), 'status' => $video ? 'pending' : null]),
            );
        }

        $image = Image::decodePath($file->getRealPath());
        $encoded = $image->encodeUsingFormat(Format::WEBP, quality: 82);
        $path = "posts/{$post->id}/".Str::uuid().'.webp';
        Storage::disk($this->disk())->put($path, $encoded->toString());

        return new StoredMediaDTO(
            path: $path,
            mimeType: $encoded->mediaType(),
            size: $encoded->size(),
            width: $image->width(),
            height: $image->height(),
            metadata: ['extension' => 'webp', 'original_extension' => $file->extension(), 'optimized' => true, 'quality' => 82],
        );
    }
}
