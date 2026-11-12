<?php

declare(strict_types=1);

namespace App\Models;

use App\Enum\TimelinePostType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'comment_id', 'read_at'])]
class ActivityNotification extends Model
{
    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param Builder<ActivityNotification> $query */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id)
            ->whereHas('comment.post', fn (Builder $posts) => $posts->visibleTo($user)
                ->where('post_type', '!=', TimelinePostType::LETTER->value));
    }

    protected function casts(): array
    {
        return ['read_at' => 'immutable_datetime'];
    }
}
