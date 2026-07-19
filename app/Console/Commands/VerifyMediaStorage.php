<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

#[Description('Verify the configured media storage disk can write, read, and delete files')]
#[Signature('media:verify-storage {--disk= : Disk to verify instead of the configured media disk} {--path=health-checks/media-storage-check.txt : Path to use for the temporary probe file}')]
class VerifyMediaStorage extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $diskName = (string) ($this->option('disk') ?: config('filesystems.media_disk', 'gcs'));
        $path = (string) $this->option('path');
        $contents = 'media-storage-check:'.now()->toIso8601String();

        try {
            $disk = Storage::disk($diskName);

            if ($disk->put($path, $contents) !== true) {
                $this->error("Unable to write test file to media storage disk [{$diskName}].");

                return self::FAILURE;
            }

            if (! $disk->exists($path)) {
                $this->error("Test file was not found on media storage disk [{$diskName}] after writing.");

                return self::FAILURE;
            }

            if ($disk->get($path) !== $contents) {
                $this->error("Test file contents could not be read back from media storage disk [{$diskName}].");

                return self::FAILURE;
            }

            $disk->delete($path);

            if ($disk->exists($path)) {
                $this->error("Test file could not be deleted from media storage disk [{$diskName}].");

                return self::FAILURE;
            }
        } catch (Throwable $exception) {
            $this->error("Media storage disk [{$diskName}] failed verification.");
            $this->line($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Media storage disk [{$diskName}] verified.");

        return self::SUCCESS;
    }
}
