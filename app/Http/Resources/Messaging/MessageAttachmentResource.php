<?php

declare(strict_types=1);

namespace App\Http\Resources\Messaging;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageAttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $removed = $this->files_removed_at !== null;
        $available = $this->resource->available();
        $hours = $this->retention_hours;
        $duration = $hours.' '.($hours === 1 ? 'hour' : 'hours');

        return [
            'id' => $this->id,
            'width' => $this->width, 'height' => $this->height,
            'expires_at' => $this->expires_at->toISOString(),
            'retention_hours' => $hours,
            'status' => $removed ? 'removed' : ($available ? 'available' : 'expired'),
            'placeholder' => $removed ? 'This image is older than '.$duration.' and has been removed.' : 'This image is older than '.$duration.' and is no longer available.',
            'url' => $available ? route('messaging.attachments.show', $this->id) : null,
            'thumbnail_url' => $available ? route('messaging.attachments.show', ['attachment' => $this->id, 'thumbnail' => 1]) : null,
        ];
    }
}
