<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enum\TimelinePostType;
use App\Jobs\GenerateVideoThumbnail;
use App\Models\Media;
use App\Models\Post;
use App\Models\User;
use App\Services\Posts\VideoTranscoder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class VideoThumbnailTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function video(array $attributes = []): Media
    {
        Storage::fake('gcs');
        Storage::disk('gcs')->put('posts/video.mp4', 'video-content');
        return Media::factory()->for(Post::factory()->create(['post_type' => TimelinePostType::MEMORY]))->create([
            'disk' => 'gcs', 'path' => 'posts/video.mp4', 'mime_type' => 'video/mp4',
            'size' => 13, 'metadata' => ['status' => 'ready'], ...$attributes,
        ]);
    }

    #[Test]
    public function thumbnail_generation_stores_a_private_preview_without_changing_the_video(): void
    {
        $media = $this->video();
        $transcoder = Mockery::mock(VideoTranscoder::class);
        $transcoder->shouldReceive('thumbnail')->once()->andReturnUsing(function (string $input, string $output): void {
            $this->assertSame('video-content', file_get_contents($input));
            file_put_contents($output, 'thumbnail-content');
        });
        $job = new GenerateVideoThumbnail($media->id);
        $job->handle($transcoder);
        $media->refresh();
        $this->assertSame('ready', $media->metadata['thumbnail_status']);
        $this->assertSame('ready', $media->metadata['status']);
        $this->assertSame('posts/video.mp4', $media->path);
        $this->assertSame('video-content', Storage::disk('gcs')->get($media->path));
        $this->assertSame('thumbnail-content', Storage::disk('gcs')->get($media->thumbnail_path));
        $this->assertSame('private', Storage::disk('gcs')->getVisibility($media->thumbnail_path));
        $job->handle(Mockery::mock(VideoTranscoder::class));
        $this->assertCount(2, Storage::disk('gcs')->allFiles());
    }

    #[Test]
    public function thumbnail_failure_preserves_playable_video_and_does_not_publish_a_preview(): void
    {
        $media = $this->video();
        $transcoder = Mockery::mock(VideoTranscoder::class);
        $transcoder->shouldReceive('thumbnail')->once()->andThrow(new RuntimeException('Cannot extract frame'));
        $job = new GenerateVideoThumbnail($media->id);
        try {
            $job->handle($transcoder);
            $this->fail('Thumbnail extraction should have failed.');
        } catch (RuntimeException $exception) {
            $job->failed($exception);
        }
        $media->refresh();
        $this->assertNull($media->thumbnail_path);
        $this->assertSame('failed', $media->metadata['thumbnail_status']);
        $this->assertSame('ready', $media->metadata['status']);
        $this->assertSame('video-content', Storage::disk('gcs')->get($media->path));
        $this->assertCount(1, Storage::disk('gcs')->allFiles());
    }

    #[Test]
    public function thumbnails_and_their_status_urls_respect_post_visibility_and_deletion(): void
    {
        $media = $this->video(['thumbnail_path' => 'posts/preview.jpg']);
        Storage::disk('gcs')->put($media->thumbnail_path, 'private-preview');
        $author = $media->post->author;
        $media->post->update(['post_type' => TimelinePostType::LETTER]);
        $this->actingAs($author)->get(route('media.thumbnail', $media))->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')->assertStreamedContent('private-preview');
        $this->get(route('media.status', $media))->assertJsonPath('thumbnailUrl', route('media.thumbnail', $media, false));
        $this->actingAs(User::factory()->create())->get(route('media.thumbnail', $media))->assertNotFound();
        $media->post->delete();
        $this->actingAs($author)->get(route('media.thumbnail', $media))->assertNotFound();
    }

    #[Test]
    public function videos_without_previews_return_404_for_the_thumbnail_endpoint(): void
    {
        $media = $this->video();
        $this->actingAs($media->post->author)->get(route('media.thumbnail', $media))->assertNotFound();
        $this->get(route('media.status', $media))->assertJsonPath('thumbnailUrl', null);
    }

    #[Test]
    public function backfill_queues_only_ready_videos_missing_thumbnails(): void
    {
        Queue::fake();
        $ready = $this->video();
        $legacy = Media::factory()->for($ready->post)->create(['mime_type' => 'video/mp4', 'metadata' => null]);
        Media::factory()->for($ready->post)->create(['mime_type' => 'video/mp4', 'metadata' => ['status' => 'processing']]);
        Media::factory()->for($ready->post)->create(['mime_type' => 'video/mp4', 'thumbnail_path' => 'existing.jpg']);
        Media::factory()->for($ready->post)->create(['mime_type' => 'image/jpeg']);
        $deleted = Post::factory()->create();
        Media::factory()->for($deleted)->create(['mime_type' => 'video/mp4']);
        $deleted->delete();
        $this->artisan('videos:generate-thumbnails')->expectsOutput('Queued 2 video thumbnails.')->assertSuccessful();
        Queue::assertPushed(GenerateVideoThumbnail::class, 2);
        foreach ([$ready, $legacy] as $video) {
            Queue::assertPushed(GenerateVideoThumbnail::class, fn ($job) => $job->mediaId === $video->id && $job->connection === 'video');
            $this->assertSame('pending', $video->fresh()->metadata['thumbnail_status']);
        }
    }
}
