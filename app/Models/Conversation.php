<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['first_participant_id', 'second_participant_id', 'is_group', 'name', 'created_by_user_id', 'creation_request_id'])]
class Conversation extends Model
{
    protected function casts(): array
    {
        return ['is_group' => 'boolean'];
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(MessagingParticipant::class, 'conversation_memberships', 'conversation_id', 'participant_id')
            ->withPivot('last_read_message_id')->withTimestamps()->orderBy('messaging_participants.id');
    }

    public function canSendMessages(): bool
    {
        return $this->participants()->whereHas('user')->count() >= 2;
    }

    public function firstParticipant(): BelongsTo
    {
        return $this->belongsTo(MessagingParticipant::class, 'first_participant_id');
    }

    public function secondParticipant(): BelongsTo
    {
        return $this->belongsTo(MessagingParticipant::class, 'second_participant_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(ConversationMembership::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function scopeForUser(Builder $query, User $user): void
    {
        $query->whereHas('memberships.participant', fn (Builder $participants) => $participants->where('user_id', $user->id));
    }

    public function participantFor(User $user): ?MessagingParticipant
    {
        return $this->participants()->where('user_id', $user->id)->first();
    }
}
