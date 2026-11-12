<?php

declare(strict_types=1);

namespace App\Models;

use App\Enum\TimelinePostType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'comment_id', 'heart_id', 'read_at'])]
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

    public function heart(): BelongsTo
    {
        return $this->belongsTo(Heart::class);
    }

    public function relatedPost(): ?Post
    {
        return $this->comment_id !== null ? $this->comment?->post : $this->heart?->post;
    }

    public function actor(): ?User
    {
        return $this->comment_id !== null ? $this->comment?->author : $this->heart?->user;
    }

    /** @param Builder<ActivityNotification> $query */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $visiblePosts = fn (Builder $posts) => $posts->visibleTo($user)
            ->where('post_type', '!=', TimelinePostType::LETTER->value);

        return $query->where('user_id', $user->id)->where(fn (Builder $activity) => $activity
            ->whereHas('comment.post', $visiblePosts)->orWhereHas('heart.post', $visiblePosts));
    }

    protected function casts(): array
    {
        return ['read_at' => 'immutable_datetime'];
    }
}
