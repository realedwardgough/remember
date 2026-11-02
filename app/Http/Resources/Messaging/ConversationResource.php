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
            'is_group' => $this->is_group,
            'name' => $this->name,
            'participants' => ParticipantResource::collection($this->participants),
            'read_receipts' => $this->participants->filter(fn ($person) => $person->user_id !== null && $person->user_id !== $request->user()->id)
                ->map(fn ($person) => ['participant_id' => $person->id, 'last_read_message_id' => $person->pivot->last_read_message_id])->values(),
            'recipient_last_read_message_id' => $this->is_group ? null : $this->participants
                ->first(fn ($person) => $person->user_id !== $request->user()->id)?->pivot->last_read_message_id,
            'can_send' => $this->participants->filter(fn ($person) => $person->user !== null)->count() >= 2,
            'latest_message' => new MessageResource($this->whenLoaded('latestMessage')),
            'unread_count' => $this->whenHas('unread_count'),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
