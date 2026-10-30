<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Models\MessageAttachment;
use App\Services\Messaging\MessagingUpdates;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class RemoveExpiredMessageImage
{
    public function handle(int $attachmentId): void
    {
        DB::transaction(function () use ($attachmentId): void {
            $attachment = MessageAttachment::query()->lockForUpdate()->find($attachmentId);
            if ($attachment === null || $attachment->files_removed_at !== null) {
                return;
            }
            $expired = $attachment->expires_at->isPast() || $attachment->expires_at->equalTo(now());
            $abandoned = $attachment->message_id === null && $attachment->uploaded_at->lte(now()->subHour());
            $incomplete = $attachment->ready_at === null && $attachment->uploaded_at->lte(now()->subMinutes(15));
            if (! $expired && ! $abandoned && ! $incomplete) {
                return;
            }
            if ($attachment->disk !== 'message_gcs'
                || config('filesystems.disks.message_gcs.driver') !== 'gcs'
                || ! config('filesystems.disks.message_gcs.bucket')) {
                throw new RuntimeException('Message image cleanup cannot operate on another disk.');
            }
            $disk = Storage::disk('message_gcs');
            foreach ([$attachment->path, $attachment->thumbnail_path] as $path) {
                if (! $disk->delete($path)) {
                    throw new RuntimeException('Message image deletion was not confirmed.');
                }
            }
            $attachment->update(['files_removed_at' => now()]);
            if ($attachment->message_id !== null) {
                app(MessagingUpdates::class)->dispatch($attachment->conversation_id, $attachment->message_id);
            }
        });
    }
}
