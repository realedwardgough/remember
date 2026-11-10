<?php

declare(strict_types=1);

namespace App\Queries;

use App\Http\Resources\MediaResource;
use App\Models\Media;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class GetGalleryVideos
{
    /** @return list<array<string, mixed>> */
    public function handle(User $viewer): array
    {
        $request = Request::create('/videos');
        $request->setUserResolver(static fn (): User => $viewer);

        return Media::query()
            ->with('post:id,title,published_at')
            ->whereHas('post', fn (Builder $query): Builder => $query->visibleTo($viewer))
            ->where('mime_type', 'like', 'video/%')
            ->latest()->orderByDesc('id')
            ->get()
            ->map(fn (Media $media): array => [
                ...MediaResource::make($media)->toArray($request),
                'postId' => $media->post_id,
                'postTitle' => $media->post?->title,
                'postDate' => $media->post?->published_at?->format('j F Y'),
            ])->all();
    }
}
