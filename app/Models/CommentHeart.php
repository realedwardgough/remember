<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Services\Posts\TimelineCache;

/**
 * @property int $id
 * @property int $comment_id
 * @property int $user_id
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Models\Comment $comment
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommentHeart newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommentHeart newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommentHeart query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommentHeart whereCommentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommentHeart whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommentHeart whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommentHeart whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CommentHeart whereUserId($value)
 * @mixin \Eloquent
 */
#[Fillable(['comment_id', 'user_id'])]
class CommentHeart extends Model
{
    protected static function booted(): void
    {
        $invalidate = static function (self $heart): void {
            $ids = Comment::query()->whereKey(array_filter([
                $heart->comment_id, $heart->getOriginal('comment_id'),
            ]))->pluck('post_id');
            foreach ($ids->unique() as $id) {
                TimelineCache::invalidate((int) $id);
            }
        };
        static::saved($invalidate);
        static::deleted($invalidate);
    }

    public function comment(): BelongsTo
    {
        return $this->belongsTo(related: Comment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(related: User::class);
    }
}
