<?php

declare(strict_types=1);

namespace App\Http\Resources\Messaging;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if ($this->resource->relationLoaded('latestMessage') && $this->latestMessage !== null) {
            $this->latestMessage->setRelation('conversation', $this->resource);
        }
        return [
            'id' => $this->id,
            'participants' => ParticipantResource::collection(collect([$this->firstParticipant, $this->secondParticipant])),
            'recipient_last_read_message_id' => $this->memberships
                ->firstWhere('participant_id', $this->firstParticipant->user_id === $request->user()->id
                    ? $this->second_participant_id : $this->first_participant_id)?->last_read_message_id,
            'can_send' => $this->firstParticipant->user !== null && $this->secondParticipant->user !== null,
            'latest_message' => new MessageResource($this->whenLoaded('latestMessage')),
            'unread_count' => $this->whenHas('unread_count'),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
