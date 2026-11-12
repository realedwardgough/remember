<?php

declare(strict_types=1);

namespace App\Services\Posts;

use App\Models\Media;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class LocalMediaCache
{
    /** @return resource */
    public function readStream(Media $media, string $path, ?int $expectedSize = null)
    {
        $budget = (int) config('media-cache.max_bytes');
        $limit = min($budget, (int) config('media-cache.max_file_bytes'));
        if (! config('media-cache.enabled') || $limit < 1 || ($expectedSize !== null && $expectedSize > $limit)
            || config("filesystems.disks.{$media->disk}.driver") !== 'gcs') {
            return Storage::disk($media->disk)->readStream($path);
        }

        $directory = config('media-cache.path');
        $key = hash('sha256', json_encode([
            $media->disk, config("filesystems.disks.{$media->disk}.bucket"),
            config("filesystems.disks.{$media->disk}.path_prefix"),
            $path, $expectedSize, $media->updated_at?->toISOString(),
        ], JSON_THROW_ON_ERROR));
        $destination = $directory.'/'.$key.'.cache';
        if (($stream = @fopen($destination, 'rb')) !== false) {
            @touch($destination);
            return $stream;
        }

        // One writer bounds disk use. Other cache misses continue directly to storage.
        $lock = null;
        $locked = false;
        $source = null;
        $target = null;
        $temporary = $directory.'/download.part';
        try {
            if (! is_dir($directory) && ! @mkdir($directory, 0700, true) && ! is_dir($directory)) {
                throw new RuntimeException('Could not create the private media cache directory.');
            }
            $lock = @fopen($directory.'/cache.lock', 'c');
            if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
                return Storage::disk($media->disk)->readStream($path);
            }
            $locked = true;
            // Another request may have populated this file before we acquired the lock.
            if (($stream = @fopen($destination, 'rb')) !== false) {
                @touch($destination);
                return $stream;
            }

            @unlink($temporary);
            $this->makeRoom($directory, $budget - $limit);
            $source = Storage::disk($media->disk)->readStream($path);
            $target = @fopen($temporary, 'wb');
            if (! is_resource($source) || $target === false) {
                throw new RuntimeException('Could not populate the private media cache.');
            }
            @chmod($temporary, 0600);
            $size = stream_copy_to_stream($source, $target, $limit);
            if ($size === false || ! feof($source)) {
                // Detect oversize objects without ever writing beyond the configured limit.
                $extra = fread($source, 1);
                if ($size === false || $extra !== '') {
                    throw new RuntimeException('Media exceeds the local cache entry limit.');
                }
            }
            if ($expectedSize !== null && $size !== $expectedSize) {
                throw new RuntimeException('Media size does not match the local cache entry.');
            }
            fclose($target);
            $target = null;
            if (! @rename($temporary, $destination)) {
                throw new RuntimeException('Could not publish the local media cache entry.');
            }
            $stream = @fopen($destination, 'rb');
            if ($stream === false) {
                throw new RuntimeException('Could not open the local media cache entry.');
            }
            return $stream;
        } catch (Throwable $exception) {
            // Cache availability must not determine whether authorized media can be viewed.
            report($exception);
        } finally {
            if (is_resource($source)) {
                fclose($source);
            }
            if (is_resource($target)) {
                fclose($target);
            }
            if (is_resource($lock)) {
                // Only the owner may remove an in-progress download.
                if ($locked) {
                    @unlink($temporary);
                    flock($lock, LOCK_UN);
                }
                fclose($lock);
            }
        }

        return Storage::disk($media->disk)->readStream($path);
    }

    private function makeRoom(string $directory, int $available): void
    {
        $files = glob($directory.'/*.cache') ?: [];
        usort($files, static fn (string $a, string $b): int => filemtime($a) <=> filemtime($b));
        $total = array_sum(array_map('filesize', $files));
        foreach ($files as $file) {
            if ($total <= $available) {
                break;
            }
            $size = filesize($file);
            if (! @unlink($file)) {
                throw new RuntimeException('Could not evict an old media cache entry.');
            }
            $total -= $size;
        }
    }
}
