<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['message_id', 'post_id', 'original_post_id'])]
class MessagePostShare extends Model
{
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    protected function casts(): array
    {
        return ['original_post_id' => 'integer'];
    }
}
