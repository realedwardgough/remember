<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Media;
use App\Http\Resources\MediaResource;
use App\Services\Posts\LocalMediaCache;
use App\Services\Posts\VideoStream;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    public function show(Request $request, Media $media): Response|StreamedResponse
    {
        $this->authorizeMedia($request, $media);

        if ($media->isVideo()) {
            abort_unless(($media->metadata['status'] ?? 'ready') === 'ready', 425);
            return $this->video($request, $media);
        }

        return $this->cachedMedia($request, $media, $media->path, $media->mime_type, $media->size);
    }

    private function cachedMedia(Request $request, Media $media, string $path, string $mimeType, ?int $size = null): Response|StreamedResponse
    {

        // Recheck access before validating a browser's cached copy. Unchanged
        // media can then be reused without contacting cloud storage at all.
        $cached = response('', 200, [
            'Cache-Control' => 'private, no-cache, max-age=0, must-revalidate',
        ])->setEtag(hash('sha256', json_encode([
            $request->user()->id,
            $media->id,
            $media->disk,
            $path,
            $size,
            $mimeType,
            $media->updated_at?->toISOString(),
        ], JSON_THROW_ON_ERROR)), weak: true);
        $cached->setLastModified($media->updated_at);

        if ($cached->isNotModified($request)) {
            return $cached;
        }

        $headers = [...$cached->headers->all(), 'Content-Type' => $mimeType, 'X-Content-Type-Options' => 'nosniff'];
        $name = $path === $media->path ? ($media->original_name ?? basename($path)) : basename($path);
        $headers['Content-Disposition'] = $cached->headers->makeDisposition('inline', $name, str_replace('%', '', Str::ascii($name)));
        if ($size !== null) {
            $headers['Content-Length'] = $size;
        }
        if ($request->isMethod('HEAD')) {
            return response('', 200, $headers);
        }

        return response()->stream(function () use ($media, $path, $size): void {
            $stream = app(LocalMediaCache::class)->readStream($media, $path, $size);
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, 200, $headers);
    }

    public function download(Request $request, Media $media): StreamedResponse
    {
        $this->authorizeMedia($request, $media);

        return Storage::disk($media->disk)->download(
            path: $media->path,
            name: $media->original_name,
            headers: [
                'Content-Type' => $media->mime_type,
            ],
        );
    }

    public function thumbnail(Request $request, Media $media): Response|StreamedResponse
    {
        $this->authorizeMedia($request, $media);
        abort_unless($media->isVideo() && $media->thumbnail_path !== null, 404);

        return $this->cachedMedia($request, $media, $media->thumbnail_path, 'image/jpeg');
    }

    public function status(Request $request, Media $media): JsonResponse
    {
        $this->authorizeMedia($request, $media);

        return response()->json(MediaResource::make($media)->toArray($request))
            ->header('Cache-Control', 'private, no-store');
    }

    private function video(Request $request, Media $media): Response|StreamedResponse
    {
        $size = $media->size;
        $start = 0;
        $end = $size - 1;
        $partial = false;
        $range = $request->header('Range');
        // Multiple ranges are ignored; single byte ranges support browser seeking.
        if ($range !== null && ! str_contains($range, ',')) {
            if (! preg_match('/^bytes=(\d*)-(\d*)$/D', $range, $matches)
                || ($matches[1] === '' && $matches[2] === '')) {
                return response('', 416, ['Content-Range' => "bytes */{$size}"]);
            }
            if ($matches[1] === '') {
                $start = max(0, $size - (int) $matches[2]);
            } else {
                $start = (int) $matches[1];
                $end = $matches[2] === '' ? $end : min($end, (int) $matches[2]);
            }
            if ($start > $end || $start >= $size) {
                return response('', 416, ['Content-Range' => "bytes */{$size}"]);
            }
            $partial = true;
        }
        $headers = [
            'Content-Type' => 'video/mp4', 'Accept-Ranges' => 'bytes',
            'Content-Length' => (string) ($end - $start + 1),
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ];
        if ($partial) {
            $headers['Content-Range'] = "bytes {$start}-{$end}/{$size}";
        }
        if ($request->isMethod('HEAD')) {
            return response('', $partial ? 206 : 200, $headers);
        }

        return response()->stream(function () use ($media, $start, $end): void {
            $stream = app(VideoStream::class)->open($media, $start, $end);
            try {
                $remaining = $end - $start + 1;
                while ($remaining > 0 && ! feof($stream)) {
                    $chunk = fread($stream, min(65536, $remaining));
                    if ($chunk === false || $chunk === '') {
                        break;
                    }
                    echo $chunk;
                    $remaining -= strlen($chunk);
                }
            } finally {
                fclose($stream);
            }
        }, $partial ? 206 : 200, $headers);
    }

    private function authorizeMedia(Request $request, Media $media): void
    {
        abort_unless(
            $media->post()->visibleTo($request->user())->exists(),
            404,
        );
    }
}
