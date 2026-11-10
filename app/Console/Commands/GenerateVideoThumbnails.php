<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\GenerateVideoThumbnail;
use App\Models\Media;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Queue thumbnails for existing videos that do not have one')]
#[Signature('videos:generate-thumbnails')]
class GenerateVideoThumbnails extends Command
{
    public function handle(): int
    {
        $count = 0;
        Media::query()->where('mime_type', 'like', 'video/%')->whereNull('thumbnail_path')
            ->whereHas('post')->chunkById(100, function ($media) use (&$count): void {
                foreach ($media as $video) {
                    if (($video->metadata['status'] ?? 'ready') !== 'ready') {
                        continue;
                    }
                    $video->update(['metadata' => [...($video->metadata ?? []), 'thumbnail_status' => 'pending']]);
                    GenerateVideoThumbnail::dispatch($video->id)->afterCommit();
                    $count++;
                }
            });
        $this->info("Queued {$count} video thumbnails.");
        return self::SUCCESS;
    }
}
