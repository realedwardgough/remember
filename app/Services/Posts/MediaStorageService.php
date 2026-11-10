<?php

declare(strict_types=1);

namespace App\Services\Posts;

use App\DTOs\StoredMediaDTO;
use App\Models\Post;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Exceptions\ImageDecoderException;
use Illuminate\Validation\ValidationException;

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

        $dimensions = @getimagesize($file->getRealPath());
        [$width, $height] = $dimensions === false ? [0, 0] : [$dimensions[0], $dimensions[1]];
        $limit = ini_parse_quantity((string) ini_get('memory_limit'));
        if ($width < 1 || $height < 1 || $width > 8192 || $height > 8192 || $width * $height > 20_000_000
            || ($limit > 0 && $width * $height * 12 + $file->getSize() * 2 > $limit - memory_get_usage(true))) {
            throw ValidationException::withMessages(['media' => 'Choose a photo up to 20 megapixels and 8192 pixels on either edge.']);
        }
        $manager = new ImageManager(config('intervention-image.driver', Driver::class), autoOrientation: true, decodeAnimation: false, strip: true);
        try {
            $image = $manager->decodePath($file->getRealPath())->scaleDown(width: 4096, height: 4096);
        } catch (ImageDecoderException) {
            throw ValidationException::withMessages(['media' => 'Choose a valid JPEG, PNG, WebP, or GIF photo.']);
        }
        $encoded = $image->encodeUsingFormat(Format::WEBP, quality: 82);
        $path = "posts/{$post->id}/".Str::uuid().'.webp';
        Storage::disk($this->disk())->put($path, $encoded->toString(), ['visibility' => 'private']);

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
