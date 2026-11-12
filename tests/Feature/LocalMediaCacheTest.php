<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enum\TimelinePostType;
use App\Models\Media;
use App\Models\Post;
use App\Services\Posts\LocalMediaCache;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LocalMediaCacheTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = storage_path('framework/testing/media-cache-'.Str::uuid());
        config(['media-cache.enabled' => true, 'media-cache.path' => $this->directory]);
        Storage::fake('gcs');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    private function photo(string $path = 'photo.webp', string $contents = 'photo-content'): Media
    {
        Storage::disk('gcs')->put($path, $contents);
        return Media::factory()->for(Post::factory()->create(['post_type' => TimelinePostType::MEMORY]))->create([
            'disk' => 'gcs', 'path' => $path, 'mime_type' => 'image/webp', 'size' => strlen($contents),
        ]);
    }

    private function read(Media $media): string
    {
        $stream = app(LocalMediaCache::class)->readStream($media, $media->path, $media->size);
        try {
            return stream_get_contents($stream);
        } finally {
            fclose($stream);
        }
    }

    #[Test]
    public function repeat_reads_reuse_private_bytes_without_another_storage_request(): void
    {
        $media = $this->photo();
        $disk = Storage::disk('gcs');
        Storage::shouldReceive('disk')->with('gcs')->once()->andReturn($disk);
        $this->assertSame('photo-content', $this->read($media));
        $this->assertSame('photo-content', $this->read($media));
        $this->assertSame(0700, fileperms($this->directory) & 0777);
        $this->assertSame(0600, fileperms(glob($this->directory.'/*.cache')[0]) & 0777);
    }

    #[Test]
    public function replacement_paths_and_versions_do_not_reuse_previous_bytes(): void
    {
        $media = $this->photo();
        $this->assertSame('photo-content', $this->read($media));
        Storage::disk('gcs')->put('replacement.webp', 'new-photo');
        $media->update(['path' => 'replacement.webp', 'size' => 9]);
        $this->assertSame('new-photo', $this->read($media));

        $this->travel(2)->seconds();
        Storage::disk('gcs')->put('replacement.webp', 'different');
        $media->touch();
        $this->assertSame('different', $this->read($media));
    }

    #[Test]
    public function disk_budget_is_bounded_and_oversize_files_bypass_the_cache(): void
    {
        config(['media-cache.max_bytes' => 12, 'media-cache.max_file_bytes' => 8]);
        foreach (range(1, 5) as $id) {
            $this->assertSame('data', $this->read($this->photo($id.'.webp', 'data')));
            $files = glob($this->directory.'/*.cache');
            $this->assertLessThanOrEqual(12, array_sum(array_map('filesize', $files)));
        }
        $files = glob($this->directory.'/*.cache');
        $this->assertSame('large-photo', $this->read($this->photo('large.webp', 'large-photo')));
        $this->assertSame($files, glob($this->directory.'/*.cache'));
        $this->assertFileDoesNotExist($this->directory.'/download.part');
    }

    #[Test]
    public function disabled_or_busy_cache_falls_back_without_touching_another_download(): void
    {
        $media = $this->photo();
        config(['media-cache.enabled' => false]);
        $this->assertSame('photo-content', $this->read($media));
        $this->assertDirectoryDoesNotExist($this->directory);
        config(['media-cache.enabled' => true]);
        File::makeDirectory($this->directory, 0700, true);
        $lock = fopen($this->directory.'/cache.lock', 'c');
        flock($lock, LOCK_EX);
        file_put_contents($this->directory.'/download.part', 'another request');
        try {
            $this->assertSame('photo-content', $this->read($media));
            $this->assertSame('another request', file_get_contents($this->directory.'/download.part'));
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    #[Test]
    public function a_warm_cache_still_requires_authorization_and_a_live_post(): void
    {
        $media = $this->photo();
        $author = $media->post->author;
        $viewer = \App\Models\User::factory()->create();
        $this->actingAs($viewer)->get(route('media.show', $media))->assertOk()->assertStreamedContent('photo-content');
        $this->assertCount(1, glob($this->directory.'/*.cache'));
        $media->post->update(['post_type' => TimelinePostType::LETTER]);
        $this->get(route('media.show', $media))->assertNotFound();
        $this->actingAs($author)->get(route('media.show', $media))->assertOk()->assertStreamedContent('photo-content');
        $media->post->delete();
        $this->get(route('media.show', $media))->assertNotFound();
    }

    #[Test]
    public function thumbnails_are_reused_locally_after_browser_cache_misses(): void
    {
        $media = $this->photo();
        $media->update(['mime_type' => 'video/mp4', 'thumbnail_path' => 'preview.jpg']);
        Storage::disk('gcs')->put('preview.jpg', 'preview-bytes');
        $this->actingAs($media->post->author)->get(route('media.thumbnail', $media))
            ->assertOk()->assertStreamedContent('preview-bytes');
        Storage::shouldReceive('disk')->never();
        $this->get(route('media.thumbnail', $media))->assertOk()->assertStreamedContent('preview-bytes');
        $media->post->delete();
        $this->get(route('media.thumbnail', $media))->assertNotFound();
    }

    #[Test]
    public function unavailable_cache_directory_falls_back_to_storage(): void
    {
        $media = $this->photo();
        File::makeDirectory($this->directory, 0700, true);
        file_put_contents($this->directory.'/not-a-directory', 'file');
        config(['media-cache.path' => $this->directory.'/not-a-directory']);
        $this->assertSame('photo-content', $this->read($media));
    }

    #[Test]
    public function mismatched_or_unexpectedly_large_objects_never_publish_partial_entries(): void
    {
        $media = $this->photo();
        $media->size--;
        $this->assertSame('photo-content', $this->read($media));
        $this->assertSame([], glob($this->directory.'/*.cache'));
        $this->assertFileDoesNotExist($this->directory.'/download.part');

        config(['media-cache.max_bytes' => 4, 'media-cache.max_file_bytes' => 4]);
        $stream = app(LocalMediaCache::class)->readStream($media, $media->path);
        try {
            $this->assertSame('photo-content', stream_get_contents($stream));
        } finally {
            fclose($stream);
        }
        $this->assertSame([], glob($this->directory.'/*.cache'));
        $this->assertFileDoesNotExist($this->directory.'/download.part');
    }
}
