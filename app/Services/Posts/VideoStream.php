<?php

declare(strict_types=1);

namespace App\Services\Posts;

use App\Models\Media;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\PathPrefixer;
use RuntimeException;
use Spatie\GoogleCloudStorage\GoogleCloudStorageAdapter;

class VideoStream
{
    /** @return resource */
    public function open(Media $media, int $start, int $end)
    {
        $disk = Storage::disk($media->disk);
        if ($disk instanceof GoogleCloudStorageAdapter) {
            $config = $disk->getConfig();
            $prefixer = new PathPrefixer($config['root'] ?? $config['path_prefix'] ?? '');
            $stream = $disk->getClient()->bucket($config['bucket'])
                ->object($prefixer->prefixPath($media->path))
                ->downloadAsStream(['restOptions' => [
                    'stream' => true,
                    'headers' => ['Range' => "bytes={$start}-{$end}"],
                ]])->detach();
            if (! is_resource($stream)) {
                throw new RuntimeException('Could not open the requested video range.');
            }
            return $stream;
        }

        $stream = $disk->readStream($media->path);
        abort_unless(is_resource($stream), 404);
        if ($start > 0 && (! stream_get_meta_data($stream)['seekable'] || fseek($stream, $start) !== 0)) {
            $remaining = $start;
            while ($remaining > 0 && ! feof($stream)) {
                $chunk = fread($stream, min(65536, $remaining));
                if ($chunk === false || $chunk === '') {
                    fclose($stream);
                    throw new RuntimeException('Could not seek to the requested video range.');
                }
                $remaining -= strlen($chunk);
            }
            if ($remaining !== 0) {
                fclose($stream);
                throw new RuntimeException('The requested video range is unavailable.');
            }
        }
        return $stream;
    }
}
