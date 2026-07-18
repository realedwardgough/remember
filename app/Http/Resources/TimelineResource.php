<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimelineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'type' => $this->resource->post_type->value,
            'title' => $this->resource->title,
            'content' => $this->resource->content ?? '',
            'date' => $this->resource->published_at->format('j F Y'),
            'datetime' => $this->resource->published_at->toDateString(),
            'author' => $this->resource->author?->username ?? 'family',
            'initial' => $this->resource->post_type->initial(),
            'images' => $this->resource->media
                ->filter(fn (Media $media): bool => $media->isImage())
                ->values()
                ->map(fn (Media $media): array => MediaResource::make($media)->toArray($request))
                ->all(),
            'files' => $this->resource->media
                ->reject(fn (Media $media): bool => $media->isImage())
                ->values()
                ->map(fn (Media $media): array => MediaResource::make($media)->toArray($request))
                ->all(),
            'tags' => $this->resource->tags->map(fn ($tag): string => '#'.$tag->name)->all(),
            'comments' => CommentResource::collection($this->resource->comments)->resolve($request),
            'commentsCount' => $this->resource->comments->count(),
            'heartsCount' => $this->resource->hearts_count,
            'heartedByViewer' => (bool) $this->resource->hearted_by_viewer,
            'canEdit' => $request->user()->id === $this->resource->author_id,
            'badgeClass' => $this->resource->post_type->badgeClass(),
            'typeClass' => $this->resource->post_type->typeClass(),
        ];
    }
}
