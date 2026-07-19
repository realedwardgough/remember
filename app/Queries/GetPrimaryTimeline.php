<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Timeline;

class GetPrimaryTimeline
{
    public function handle(): Timeline
    {
        return Timeline::query()->firstOrCreate(
            ['identifier' => Timeline::PRIMARY_IDENTIFIER],
            [
                'name' => (string) config('app.name'),
                'description' => null,
                'setup_completed_at' => now(),
            ],
        );
    }
}
