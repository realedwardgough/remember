<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Media;
use App\Services\Posts\VideoTranscoder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class GenerateVideoThumbnail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 300;
    public bool $failOnTimeout = true;

    public function __construct(public readonly int $mediaId)
    {
        $this->onConnection('video')->onQueue('videos');
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('video-thumbnail-'.$this->mediaId))->releaseAfter(30)->expireAfter(360)];
    }

    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(VideoTranscoder $transcoder): void
    {
        $media = Media::query()->find($this->mediaId);
        if ($media === null || ! $media->isVideo() || $media->thumbnail_path !== null
            || ($media->metadata['status'] ?? 'ready') !== 'ready' || ! $media->post()->exists()) {
            return;
        }
        $media->update(['metadata' => [...($media->metadata ?? []), 'thumbnail_status' => 'processing']]);
        $directory = storage_path('app/video-processing/'.Str::uuid());
        File::ensureDirectoryExists($directory, 0700);
        $disk = Storage::disk($media->disk);
        $destination = "posts/{$media->post_id}/videos/thumbnails/".Str::uuid().'.jpg';
        $published = false;
        try {
            $input = $directory.'/input.mp4';
            $source = $disk->readStream($media->path);
            $target = fopen($input, 'wb');
            try {
                if (! is_resource($source) || ! is_resource($target)
                    || stream_copy_to_stream($source, $target) !== $media->size) {
                    throw new RuntimeException('Could not download video for thumbnail.');
                }
            } finally {
                if (is_resource($source)) {
                    fclose($source);
                }
                if (is_resource($target)) {
                    fclose($target);
                }
            }
            $output = $directory.'/thumbnail.jpg';
            $transcoder->thumbnail($input, $output);
            $bytes = file_get_contents($output);
            if ($bytes === false || ! $disk->put($destination, $bytes, ['visibility' => 'private', 'ContentType' => 'image/jpeg'])
                || ! hash_equals(hash('sha256', $bytes), hash('sha256', $disk->get($destination)))) {
                throw new RuntimeException('Could not verify stored video thumbnail.');
            }
            $published = DB::transaction(function () use ($media, $destination): bool {
                $current = Media::query()->lockForUpdate()->find($media->id);
                if ($current === null || $current->path !== $media->path || $current->thumbnail_path !== null) {
                    return false;
                }
                $current->update([
                    'thumbnail_path' => $destination,
                    'metadata' => [...($current->metadata ?? []), 'thumbnail_status' => 'ready'],
                ]);
                return true;
            });
        } finally {
            File::deleteDirectory($directory);
            if (! $published) {
                $disk->delete($destination);
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        $media = Media::query()->find($this->mediaId);
        if ($media !== null && $media->thumbnail_path === null) {
            $media->update(['metadata' => [...($media->metadata ?? []), 'thumbnail_status' => 'failed']]);
        }
    }
}
