<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaStorageTest extends TestCase
{
    public function test_media_storage_verification_command_checks_the_configured_disk(): void
    {
        config(['filesystems.media_disk' => 'gcs']);

        Storage::fake('gcs');

        $path = 'health-checks/media-storage-test.txt';

        $this
            ->artisan('media:verify-storage', ['--path' => $path])
            ->expectsOutput('Media storage disk [gcs] verified.')
            ->assertSuccessful();

        Storage::disk('gcs')->assertMissing($path);
    }
}
