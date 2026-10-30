<?php

declare(strict_types=1);

namespace App\Http\Resources\Messaging;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'sender' => new ParticipantResource($this->sender),
            'body' => $this->body,
            'client_request_id' => $this->sender->user_id === $request->user()->id ? $this->client_request_id : null,
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
