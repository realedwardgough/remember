<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Media;
use App\Http\Resources\MediaResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
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

        // Recheck access before validating a browser's cached copy. Unchanged
        // media can then be reused without contacting cloud storage at all.
        $cached = response('', 200, [
            'Cache-Control' => 'private, no-cache, max-age=0, must-revalidate',
        ])->setEtag(hash('sha256', json_encode([
            $request->user()->id,
            $media->id,
            $media->disk,
            $media->path,
            $media->size,
            $media->mime_type,
            $media->updated_at?->toISOString(),
        ], JSON_THROW_ON_ERROR)), weak: true);
        $cached->setLastModified($media->updated_at);

        if ($cached->isNotModified($request)) {
            return $cached;
        }

        return Storage::disk($media->disk)->response(
            path: $media->path,
            name: $media->original_name,
            headers: [
                ...$cached->headers->all(),
                'Content-Type' => $media->mime_type,
                'Content-Length' => $media->size,
            ],
        );
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
            $stream = Storage::disk($media->disk)->readStream($media->path);
            abort_unless(is_resource($stream), 404);
            try {
                // GCS streams may not be seekable. Discard in bounded chunks in that case.
                if ($start > 0 && (! stream_get_meta_data($stream)['seekable'] || fseek($stream, $start) !== 0)) {
                    $skip = $start;
                    while ($skip > 0 && ! feof($stream)) {
                        $chunk = fread($stream, min(65536, $skip));
                        if ($chunk === false || $chunk === '') {
                            break;
                        }
                        $skip -= strlen($chunk);
                    }
                }
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
