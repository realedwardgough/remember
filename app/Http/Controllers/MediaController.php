<?php

namespace App\Http\Controllers;

use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    public function show(Request $request, Media $media): Response|StreamedResponse
    {
        $this->authorizeMedia($request, $media);

        return Storage::disk($media->disk)->response(
            path: $media->path,
            name: $media->original_name,
            headers: [
                'Content-Type' => $media->mime_type,
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

    private function authorizeMedia(Request $request, Media $media): void
    {
        abort_unless(
            $media->post()->visibleTo($request->user())->exists(),
            404,
        );
    }
}
