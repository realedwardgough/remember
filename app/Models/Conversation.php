<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['first_participant_id', 'second_participant_id'])]
class Conversation extends Model
{
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
        return MessagingParticipant::query()->where('user_id', $user->id)
            ->whereIn('id', [$this->first_participant_id, $this->second_participant_id])->first();
    }
}
