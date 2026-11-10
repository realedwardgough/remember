<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->original_name ?? basename($this->resource->path),
            'mimeType' => $this->resource->mime_type,
            'status' => $this->resource->metadata['status'] ?? 'ready',
            'thumbnailUrl' => $this->resource->thumbnail_path !== null
                ? route('media.thumbnail', $this->resource, false) : null,
            'thumbnailStatus' => $this->resource->metadata['thumbnail_status'] ?? null,
            'statusUrl' => route('media.status', $this->resource, false),
            'size' => $this->resource->size,
            'url' => route('media.show', $this->resource, false),
            'downloadUrl' => route('media.download', $this->resource, false),
            'width' => $this->resource->width,
            'height' => $this->resource->height,
        ];
    }
}
