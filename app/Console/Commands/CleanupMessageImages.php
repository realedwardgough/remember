<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Messaging\RemoveExpiredMessageImage;
use App\Models\MessageAttachment;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

class CleanupMessageImages extends Command
{
    protected $signature = 'messages:cleanup-images';
    protected $description = 'Remove expired and abandoned message images from their private storage';

    public function handle(RemoveExpiredMessageImage $remove): int
    {
        $failed = 0;
        $removed = 0;
        MessageAttachment::query()->whereNull('files_removed_at')
            ->where(function (Builder $query): void {
                $query->where('expires_at', '<=', now())
                    ->orWhere(fn (Builder $staged) => $staged->whereNull('message_id')->where('uploaded_at', '<=', now()->subHour()))
                    ->orWhere(fn (Builder $incomplete) => $incomplete->whereNull('ready_at')->where('uploaded_at', '<=', now()->subMinutes(15)));
            })->chunkById(100, function ($attachments) use ($remove, &$failed, &$removed): void {
                foreach ($attachments as $attachment) {
                    try {
                        $remove->handle($attachment->id);
                        $removed++;
                    } catch (Throwable $exception) {
                        report($exception);
                        $failed++;
                    }
                }
            });
        $this->info('Processed '.$removed.' message image(s); '.$failed.' cleanup failure(s).');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
