<?php

declare(strict_types=1);

namespace App\Actions;

use App\DTOs\TimelineData;
use App\Models\Timeline;

class UpdateTimeline
{
    public function handle(Timeline $timeline, TimelineData $data): Timeline
    {
        $timeline->update(['name' => $data->name, 'description' => $data->description]);

        return $timeline->refresh();
    }
}
