<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GalleryController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render(component: 'Gallery', props: [
            'images' => Media::query()
                ->with('post:id,title,published_at')
                ->whereHas(
                    relation: 'post',
                    callback: fn (Builder $query): Builder => $query->visibleTo($request->user()),
                )
                ->where('mime_type', 'like', 'image/%')
                ->latest()
                ->get()
                ->map(fn (Media $media): array => [
                    'id' => $media->id,
                    'name' => $media->original_name ?? basename($media->path),
                    'url' => route('media.show', $media, false),
                    'downloadUrl' => route('media.download', $media, false),
                    'width' => $media->width,
                    'height' => $media->height,
                    'mimeType' => $media->mime_type,
                    'size' => $media->size,
                    'postTitle' => $media->post?->title,
                    'postDate' => $media->post?->published_at?->format('j F Y'),
                ]),
        ]);
    }
}
