<?php

declare(strict_types=1);

namespace App\Http\Resources\Messaging;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ParticipantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->resource->displayName(),
            'username' => $this->user?->username,
            'deleted' => $this->user === null,
            'is_self' => $this->user_id === $request->user()->id,
        ];
    }
}
