<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property int $post_id
 * @property int $author_id
 * @property string $content
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Models\User $author
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CommentHeart> $hearts
 * @property-read int|null $hearts_count
 * @property-read \App\Models\Post|null $post
 * @method static \Database\Factories\CommentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereAuthorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment wherePostId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Comment whereUpdatedAt($value)
 * @mixin \Eloquent
 */
#[Fillable(['post_id', 'author_id', 'content'])]
class Comment extends Model
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saved(function () {
            Cache::tags(names: 'posts')->flush();
        });

        static::deleted(function (): void {
            Cache::tags(names: 'posts')->flush();
        });
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(related: Post::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(related: User::class, foreignKey: 'author_id');
    }

    public function hearts(): HasMany
    {
        return $this->hasMany(related: CommentHeart::class);
    }
}
