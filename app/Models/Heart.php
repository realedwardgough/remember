<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

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
        return $this->belongsTo(Post::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
