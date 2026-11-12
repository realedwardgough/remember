<?php

declare(strict_types=1);

namespace App\Queries;

use App\Http\Resources\MediaResource;
use App\Models\Media;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class GetGalleryVideos
{
    public function handle(User $viewer, int $page = 1): LengthAwarePaginator
    {
        $request = Request::create('/videos');
        $request->setUserResolver(static fn (): User => $viewer);

        return Media::query()
            ->with('post:id,title,published_at')
            ->whereHas('post', fn (Builder $query): Builder => $query->visibleTo($viewer))
            ->where('mime_type', 'like', 'video/%')
            ->latest()->orderByDesc('id')
            ->paginate(perPage: 24, page: $page)
            ->through(fn (Media $media): array => [
                ...MediaResource::make($media)->toArray($request),
                'postId' => $media->post_id,
                'postTitle' => $media->post?->title,
                'postDate' => $media->post?->published_at?->format('j F Y'),
            ]);
    }
}
