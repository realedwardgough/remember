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

class ProcessVideo implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 1800;
    public bool $failOnTimeout = true;

    public function __construct(public readonly int $mediaId)
    {
        $this->onConnection('video')->onQueue('videos');
    }

    public function backoff(): array
    {
        return [60, 300];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('video-'.$this->mediaId))->releaseAfter(60)->expireAfter(1860)];
    }

    public function handle(VideoTranscoder $transcoder): void
    {
        $media = Media::query()->find($this->mediaId);
        if ($media === null || ! $media->isVideo()) {
            return;
        }
        $disk = Storage::disk($media->disk);
        if (($media->metadata['status'] ?? null) === 'ready') {
            $this->deleteOriginal($media);
            return;
        }

        $media->update(['metadata' => [...($media->metadata ?? []), 'status' => 'processing']]);
        $directory = storage_path('app/video-processing/'.Str::uuid());
        File::ensureDirectoryExists($directory, 0700);
        $input = $directory.'/input';
        $output = $directory.'/output.mp4';
        $original = $media->path;
        $destination = "posts/{$media->post_id}/videos/".Str::uuid().'.mp4';
        $published = false;

        try {
            $source = $disk->readStream($original);
            $target = fopen($input, 'wb');
            try {
                if (! is_resource($source) || ! is_resource($target) || stream_copy_to_stream($source, $target) !== $media->size) {
                    throw new RuntimeException('Could not download original video.');
                }
            } finally {
                if (is_resource($source)) {
                    fclose($source);
                }
                if (is_resource($target)) {
                    fclose($target);
                }
            }
            $dimensions = $transcoder->transcode($input, $output);
            $stream = fopen($output, 'rb');
            try {
                if (! $disk->put($destination, $stream, ['visibility' => 'private', 'ContentType' => 'video/mp4'])) {
                    throw new RuntimeException('Could not store processed video.');
                }
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
            // Read the stored object back: existence alone does not prove a safe replacement.
            $stored = $disk->readStream($destination);
            try {
                if (! is_resource($stored)) {
                    throw new RuntimeException('Could not verify processed video.');
                }
                $hash = hash_init('sha256');
                hash_update_stream($hash, $stored);
                if (! hash_equals(hash_file('sha256', $output), hash_final($hash))) {
                    throw new RuntimeException('Stored video checksum does not match.');
                }
            } finally {
                if (is_resource($stored)) {
                    fclose($stored);
                }
            }

            $published = DB::transaction(function () use ($media, $original, $destination, $dimensions, $output): bool {
                $current = Media::query()->lockForUpdate()->find($media->id);
                if ($current === null || $current->path !== $original) {
                    return false;
                }
                $current->update([
                    'path' => $destination, 'mime_type' => 'video/mp4', 'size' => filesize($output),
                    ...$dimensions,
                    'original_name' => pathinfo($current->original_name ?? 'video', PATHINFO_FILENAME).'.mp4',
                    'metadata' => [...($current->metadata ?? []), 'status' => 'ready', 'optimized' => true,
                        'extension' => 'mp4', 'original_path' => $original, 'original_size' => $media->size],
                ]);
                return true;
            });
            if ($published) {
                $this->deleteOriginal($media->fresh());
            }
        } catch (Throwable $exception) {
            if (! $published) {
                $media->refresh()->update(['metadata' => [...($media->metadata ?? []), 'status' => 'pending']]);
            }
            throw $exception;
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
        if ($media !== null && ($media->metadata['status'] ?? null) !== 'ready') {
            $media->update(['metadata' => [...($media->metadata ?? []), 'status' => 'failed']]);
        }
    }

    private function deleteOriginal(Media $media): void
    {
        $original = $media->metadata['original_path'] ?? null;
        if ($original !== null && $original !== $media->path) {
            if (! Storage::disk($media->disk)->delete($original)) {
                throw new RuntimeException('Could not delete original video.');
            }
            $metadata = $media->metadata;
            unset($metadata['original_path']);
            $media->update(['metadata' => $metadata]);
        }
    }
}
