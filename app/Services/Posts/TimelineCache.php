<?php

declare(strict_types=1);

namespace App\Services\Posts;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TimelineCache
{
    public static function invalidate(?int $postId = null): void
    {
        $tag = $postId === null ? 'posts' : 'timeline-post-'.$postId;
        if ($postId === null) {
            self::invalidateSidebar();
        }
        self::invalidateTag($tag);
    }

    public static function invalidateSidebar(): void
    {
        self::invalidateTag('sidebar');
    }

    private static function invalidateTag(string $tag): void
    {
        Cache::tags([$tag])->flush();

        // A concurrent reader can refill from pre-commit data. Expire that copy too.
        if (DB::transactionLevel() > 0) {
            DB::afterCommit(static fn () => Cache::tags([$tag])->flush());
        }
    }
}
