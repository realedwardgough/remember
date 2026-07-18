<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property int $post_id
 * @property int $user_id
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Models\Post|null $post
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Heart newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Heart newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Heart query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Heart whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Heart whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Heart wherePostId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Heart whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Heart whereUserId($value)
 * @mixin \Eloquent
 */
#[Fillable(['post_id', 'user_id'])]
class Heart extends Model
{
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(related: User::class);
    }
}
