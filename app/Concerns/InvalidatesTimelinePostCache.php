<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Services\Posts\TimelineCache;

trait InvalidatesTimelinePostCache
{
    public static function bootInvalidatesTimelinePostCache(): void
    {
        $invalidate = static function (self $model): void {
            foreach (array_unique([$model->post_id, $model->getOriginal('post_id')]) as $id) {
                if ($id !== null) {
                    TimelineCache::invalidate((int) $id);
                }
            }
        };
        static::saved($invalidate);
        static::deleted($invalidate);
    }
}
