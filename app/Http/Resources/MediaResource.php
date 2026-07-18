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
            'size' => $this->resource->size,
            'url' => route('media.show', $this->resource, false),
            'downloadUrl' => route('media.download', $this->resource, false),
            'width' => $this->resource->width,
            'height' => $this->resource->height,
        ];
    }
}
