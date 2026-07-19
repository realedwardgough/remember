<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Media;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class GetGalleryImages
{
    /** @return list<array<string, int|string|null>> */
    public function handle(User $viewer): array
    {
        return Media::query()
            ->with('post:id,title,published_at')
            ->whereHas('post', fn (Builder $query): Builder => $query->visibleTo($viewer))
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
            ])->all();
    }
}
