<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'content' => $this->resource->content,
            'author_fullname' => $this->resource->author?->name ?? 'Family',
            'author' => $this->resource->author?->username ?? 'family',
            'createdAt' => $this->resource->created_at->diffForHumans(),
            'heartsCount' => $this->resource->hearts_count ?? 0,
            'heartedByViewer' => (bool) ($this->resource->hearted_by_viewer ?? false),
        ];
    }
}
