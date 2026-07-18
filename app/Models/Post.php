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
        return $this->belongsTo(User::class, 'author_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class)->orderBy('sort_order');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->oldest();
    }

    public function hearts(): HasMany
    {
        return $this->hasMany(Heart::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->withTimestamps()->orderByPivot('id');
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
