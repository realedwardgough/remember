<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['conversation_id', 'message_id', 'uploaded_by_participant_id', 'client_request_id', 'source_hash', 'disk', 'path', 'thumbnail_path', 'width', 'height', 'size', 'retention_hours', 'uploaded_at', 'expires_at', 'ready_at', 'files_removed_at'])]
class MessageAttachment extends Model
{
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function available(): bool
    {
        return $this->ready_at !== null && $this->files_removed_at === null && $this->expires_at->isFuture();
    }

    protected function casts(): array
    {
        return [
            'uploaded_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'ready_at' => 'immutable_datetime',
            'files_removed_at' => 'immutable_datetime',
            'retention_hours' => 'integer',
        ];
    }
}
