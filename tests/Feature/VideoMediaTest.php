<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enum\TimelinePostType;
use App\Jobs\ProcessVideo;
use App\Jobs\GenerateVideoThumbnail;
use App\Models\Media;
use App\Models\Post;
use App\Models\User;
use App\Services\Posts\VideoTranscoder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class VideoMediaTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['filesystems.media_disk' => 'gcs']);
        Storage::fake('gcs');
        Queue::fake([GenerateVideoThumbnail::class]);
    }

    private function video(array $metadata = ['status' => 'pending']): Media
    {
        $post = Post::factory()->for(User::factory(), 'author')->create(['post_type' => TimelinePostType::MEMORY]);
        Storage::disk('gcs')->put('posts/original.mov', 'original-video');

        return Media::factory()->for($post)->create([
            'disk' => 'gcs', 'path' => 'posts/original.mov', 'mime_type' => 'video/quicktime',
            'original_name' => 'family.mov', 'size' => 14, 'metadata' => $metadata,
        ]);
    }

    private function transcoder(): VideoTranscoder
    {
        $transcoder = Mockery::mock(VideoTranscoder::class);
        $transcoder->shouldReceive('transcode')->once()->andReturnUsing(function (string $input, string $output): array {
            $this->assertSame('original-video', file_get_contents($input));
            file_put_contents($output, 'converted-video');
            return ['width' => 1280, 'height' => 720];
        });
        return $transcoder;
    }

    #[Test]
    public function video_uploads_are_queued_and_presented_separately(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('posts.store'), [
            'title' => 'First steps', 'post_type' => 'Memory', 'published_at' => '2026-11-01',
            'media' => [UploadedFile::fake()->create('steps.mov', 12000, 'video/quicktime')],
        ])->assertRedirect(route('home'));
        $media = Media::query()->sole();
        $this->assertSame('pending', $media->metadata['status']);
        $this->assertStringContainsString('/videos/originals/', $media->path);
        Storage::disk('gcs')->assertExists($media->path);
        Queue::assertPushed(ProcessVideo::class, fn ($job) => $job->mediaId === $media->id
            && $job->connection === 'video' && $job->queue === 'videos' && $job->afterCommit);
        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
            ->has('timelinePosts.data.0.videos', 1)->has('timelinePosts.data.0.files', 0)
            ->where('timelinePosts.data.0.videos.0.status', 'pending'));
    }

    #[Test]
    public function attachment_size_limits_distinguish_videos_from_photos_and_documents(): void
    {
        Queue::fake();
        $this->actingAs(User::factory()->create())->post(route('posts.store'), [
            'title' => 'Large uploads', 'post_type' => 'Memory', 'published_at' => '2026-11-01',
            'media' => [
                UploadedFile::fake()->create('too-big.mp4', 262145, 'video/mp4'),
                UploadedFile::fake()->create('too-big.pdf', 10241, 'application/pdf'),
            ],
        ])->assertSessionHasErrors(['media.0', 'media.1']);
        $this->assertSame(0, Media::query()->count());
    }

    #[Test]
    public function processing_publishes_a_verified_mp4_then_deletes_the_original_and_is_idempotent(): void
    {
        $media = $this->video();
        $job = new ProcessVideo($media->id);
        $job->handle($this->transcoder());
        $media->refresh();
        $this->assertSame('ready', $media->metadata['status']);
        $this->assertSame('video/mp4', $media->mime_type);
        $this->assertSame('family.mp4', $media->original_name);
        Queue::assertPushed(GenerateVideoThumbnail::class, fn ($job) => $job->mediaId === $media->id);
        $this->assertSame('pending', $media->metadata['thumbnail_status']);
        $this->assertSame(1280, $media->width);
        $this->assertSame('converted-video', Storage::disk('gcs')->get($media->path));
        Storage::disk('gcs')->assertMissing('posts/original.mov');
        $job->handle(Mockery::mock(VideoTranscoder::class));
        $this->assertCount(1, Storage::disk('gcs')->allFiles());
    }

    #[Test]
    public function conversion_failure_keeps_original_and_exhausted_jobs_show_failure(): void
    {
        $media = $this->video();
        $transcoder = Mockery::mock(VideoTranscoder::class);
        $transcoder->shouldReceive('transcode')->once()->andThrow(new RuntimeException('Invalid video'));
        $job = new ProcessVideo($media->id);
        try {
            $job->handle($transcoder);
            $this->fail('Conversion should have failed.');
        } catch (RuntimeException $exception) {
            $job->failed($exception);
        }
        $this->assertSame('failed', $media->fresh()->metadata['status']);
        Storage::disk('gcs')->assertExists('posts/original.mov');
        $this->assertCount(1, Storage::disk('gcs')->allFiles());
        $job->handle($this->transcoder());
        $this->assertSame('ready', $media->fresh()->metadata['status']);
    }

    #[Test]
    public function a_corrupt_stored_replacement_never_replaces_the_original(): void
    {
        $media = $this->video();
        $disk = Storage::disk('gcs');
        $proxy = Mockery::mock($disk);
        $proxy->shouldReceive('readStream')->andReturnUsing(function (string $path) use ($disk) {
            if ($path === 'posts/original.mov') {
                return $disk->readStream($path);
            }
            $stream = fopen('php://temp', 'w+b');
            fwrite($stream, 'corrupted');
            rewind($stream);
            return $stream;
        });
        Storage::shouldReceive('disk')->with('gcs')->andReturn($proxy);
        try {
            (new ProcessVideo($media->id))->handle($this->transcoder());
            $this->fail('Corrupt output should be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('checksum', $exception->getMessage());
        }
        $this->assertSame('posts/original.mov', $media->fresh()->path);
        $this->assertTrue($disk->exists('posts/original.mov'));
        $this->assertCount(1, $disk->allFiles());
    }

    #[Test]
    public function retries_finish_deletion_without_transcoding_the_published_video_again(): void
    {
        $media = $this->video();
        $disk = Storage::disk('gcs');
        $proxy = Mockery::mock($disk);
        $firstAttempt = true;
        $proxy->shouldReceive('delete')->with('posts/original.mov')->andReturnUsing(function (string $path) use ($disk, &$firstAttempt): bool {
            if ($firstAttempt) {
                $firstAttempt = false;
                throw new RuntimeException('Deletion unavailable');
            }
            return $disk->delete($path);
        });
        Storage::shouldReceive('disk')->with('gcs')->andReturn($proxy);
        $job = new ProcessVideo($media->id);
        try {
            $job->handle($this->transcoder());
            $this->fail('Deletion should have failed.');
        } catch (RuntimeException) {
            $this->assertSame('ready', $media->fresh()->metadata['status']);
            $this->assertTrue($disk->exists($media->path));
            $this->assertTrue($disk->exists('posts/original.mov'));
        }
        $job->handle(Mockery::mock(VideoTranscoder::class));
        $this->assertFalse($disk->exists('posts/original.mov'));
        $this->assertArrayNotHasKey('original_path', $media->fresh()->metadata);
    }

    #[Test]
    public function byte_ranges_support_seeking_and_reject_unsatisfiable_ranges(): void
    {
        $media = $this->video(['status' => 'ready']);
        $media->update(['mime_type' => 'video/mp4']);
        $this->actingAs($media->post->author)->get(route('media.show', $media), ['Range' => 'bytes=2-5'])
            ->assertStatus(206)->assertHeader('Content-Range', 'bytes 2-5/14')
            ->assertHeader('Content-Length', '4')->assertHeader('Accept-Ranges', 'bytes')
            ->assertStreamedContent('igin');
        $this->get(route('media.show', $media), ['Range' => 'bytes=-5'])
            ->assertStatus(206)->assertStreamedContent('video');
        $this->get(route('media.show', $media), ['Range' => 'bytes=14-'])
            ->assertStatus(416)->assertHeader('Content-Range', 'bytes */14');
        $this->get(route('media.show', $media))->assertOk()->assertStreamedContent('original-video');
    }

    #[Test]
    public function video_status_and_playback_respect_private_letters_and_deleted_posts(): void
    {
        $media = $this->video();
        $author = $media->post->author;
        $media->post->update(['post_type' => TimelinePostType::LETTER]);
        $this->actingAs($author)->get(route('media.status', $media))->assertOk()->assertJsonPath('status', 'pending');
        $this->get(route('media.show', $media))->assertStatus(425);
        $this->actingAs(User::factory()->create())->get(route('media.status', $media))->assertNotFound();
        $this->get(route('media.show', $media), ['Range' => 'bytes=0-3'])->assertNotFound();
        $this->get(route('media.download', $media))->assertNotFound();
        $media->post->delete();
        $this->actingAs($author)->get(route('media.status', $media))->assertNotFound();
    }
}
