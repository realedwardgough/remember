<?php

declare(strict_types=1);

namespace App\Models;

use App\Enum\TimelinePostType;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property int|null $author_id
 * @property string $title
 * @property string|null $content
 * @property TimelinePostType $post_type
 * @property \Carbon\CarbonImmutable $published_at
 * @property string $visibility
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property \Carbon\CarbonImmutable|null $deleted_at
 * @property-read \App\Models\User|null $author
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Comment> $comments
 * @property-read int|null $comments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Heart> $hearts
 * @property-read int|null $hearts_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Media> $media
 * @property-read int|null $media_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Tag> $tags
 * @property-read int|null $tags_count
 * @method static \Database\Factories\PostFactory factory($count = null, $state = [])
 * @method static Builder<static>|Post newModelQuery()
 * @method static Builder<static>|Post newQuery()
 * @method static Builder<static>|Post onlyTrashed()
 * @method static Builder<static>|Post query()
 * @method static Builder<static>|Post visibleTo(\App\Models\User $user)
 * @method static Builder<static>|Post whereAuthorId($value)
 * @method static Builder<static>|Post whereContent($value)
 * @method static Builder<static>|Post whereCreatedAt($value)
 * @method static Builder<static>|Post whereDeletedAt($value)
 * @method static Builder<static>|Post whereId($value)
 * @method static Builder<static>|Post wherePostType($value)
 * @method static Builder<static>|Post wherePublishedAt($value)
 * @method static Builder<static>|Post whereTitle($value)
 * @method static Builder<static>|Post whereUpdatedAt($value)
 * @method static Builder<static>|Post whereVisibility($value)
 * @method static Builder<static>|Post withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|Post withoutTrashed()
 * @mixin \Eloquent
 */
#[Fillable(['author_id', 'title', 'content', 'post_type', 'published_at', 'visibility'])]
#[Table(name: 'posts')]
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;
    use SoftDeletes;

    protected static function booted(): void
    {
        static::saved(function () {
            Cache::tags(names: 'posts')->flush();
        });

        static::deleted(function (): void {
            Cache::tags(names: 'posts')->flush();
        });
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(related: User::class, foreignKey: 'author_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(related: Media::class)->orderBy(column: 'sort_order');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(related: Comment::class)->oldest();
    }

    public function hearts(): HasMany
    {
        return $this->hasMany(related: Heart::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(related: Tag::class)->withTimestamps()->orderByPivot(column: 'id');
    }

    /**
     * @param  Builder<Post>  $query
     * @return Builder<Post>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $query) use ($user): void {
            $query
                ->where(column: "post_type", operator: "!=", value: TimelinePostType::LETTER->value)
                ->orWhere(function (Builder $query) use ($user): void {
                    $query->where(column: "post_type", operator: "=", value: TimelinePostType::LETTER->value)
                        ->where(column: "author_id", operator: "=", value: $user->id);
                });
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'post_type' => TimelinePostType::class,
            'published_at' => 'date',
        ];
    }
}
