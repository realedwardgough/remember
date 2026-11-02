<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['conversation_id', 'participant_id', 'last_read_message_id'])]
class ConversationMembership extends Model
{
    public function participant(): BelongsTo
    {
        return $this->belongsTo(MessagingParticipant::class, 'participant_id');
    }
}
