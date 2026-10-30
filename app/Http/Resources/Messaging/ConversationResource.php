<?php

declare(strict_types=1);

namespace App\Http\Resources\Messaging;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'participants' => ParticipantResource::collection(collect([$this->firstParticipant, $this->secondParticipant])),
            'can_send' => $this->firstParticipant->user !== null && $this->secondParticipant->user !== null,
            'latest_message' => new MessageResource($this->whenLoaded('latestMessage')),
            'unread_count' => $this->whenHas('unread_count'),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
