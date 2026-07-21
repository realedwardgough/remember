<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use App\Enum\TimelinePostType;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PostMediaTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['filesystems.media_disk' => 'gcs']);

        Cache::flush();
    }

    #[Test]
    public function authenticated_users_can_store_a_post_with_multiple_media_files(): void
    {
        Storage::fake('gcs');

        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->post(route('posts.store'), [
                'title' => 'First scan appointment',
                'content' => 'A small note for sunshine.',
                'post_type' => TimelinePostType::MILESTONE->value,
                'published_at' => '2026-03-12',
                'media' => [
                    UploadedFile::fake()->image('scan-one.jpg', 1200, 800),
                    UploadedFile::fake()->image('scan-two.jpg', 1000, 700),
                    UploadedFile::fake()->create('letter.pdf', 128, 'application/pdf'),
                ],
            ]);

        $response->assertRedirect(route('home'))
            ->assertInertiaFlash('toasts.0.message', 'Post created.')
            ->assertInertiaFlash('toasts.1.message', '2 images uploaded.');

        $post = Post::query()->with('media')->sole();

        $this->assertTrue($post->author->is($user));
        $this->assertSame('First scan appointment', $post->title);
        $this->assertSame(TimelinePostType::MILESTONE, $post->post_type);
        $this->assertSame('2026-03-12', $post->published_at->toDateString());
        $this->assertCount(3, $post->media);
        $this->assertSame([0, 1, 2], $post->media->pluck('sort_order')->all());

        foreach ($post->media as $media) {
            Storage::disk('gcs')->assertExists($media->path);
            $this->assertSame('gcs', $media->disk);
        }

        $this->assertSame('image/webp', $post->media[0]->mime_type);
        $this->assertSame('webp', pathinfo($post->media[0]->path, PATHINFO_EXTENSION));
        $this->assertSame('webp', $post->media[0]->metadata['extension']);
        $this->assertSame('jpg', $post->media[0]->metadata['original_extension']);
        $this->assertTrue($post->media[0]->metadata['optimized']);
        $this->assertNotNull($post->media[0]->width);
        $this->assertNotNull($post->media[0]->height);
        $this->assertSame('application/pdf', $post->media[2]->mime_type);
        $this->assertNull($post->media[2]->width);
        $this->assertNull($post->media[2]->height);
    }



    #[Test]
    public function home_feed_splits_images_and_files(): void
    {
        Storage::fake('gcs');

        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->post(route('posts.store'), [
                'title' => 'First scan appointment',
                'post_type' => TimelinePostType::MILESTONE->value,
                'published_at' => '2026-03-12',
                'media' => [
                    UploadedFile::fake()->image('scan-one.jpg', 1200, 800),
                    UploadedFile::fake()->create('letter.pdf', 128, 'application/pdf'),
                ],
            ]);

        $this
            ->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                ->where('timelinePosts.data.0.images.0.name', 'scan-one.jpg')
                ->where('timelinePosts.data.0.files.0.name', 'letter.pdf')
                ->where('timelinePosts.data.0.images.0.mimeType', 'image/webp')
                ->where('timelinePosts.data.0.files.0.mimeType', 'application/pdf')
            );
    }

    #[Test]
    public function uploaded_images_are_converted_to_webp(): void
    {
        Storage::fake('gcs');

        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->post(route('posts.store'), [
                'title' => 'First scan appointment',
                'post_type' => TimelinePostType::MILESTONE->value,
                'published_at' => '2026-03-12',
                'media' => [
                    UploadedFile::fake()->image('scan-one.png', 1200, 800),
                ],
            ]);

        $media = Post::query()->sole()->media()->sole();

        Storage::disk('gcs')->assertExists($media->path);

        $this->assertSame('image/webp', $media->mime_type);
        $this->assertSame('webp', pathinfo($media->path, PATHINFO_EXTENSION));
        $this->assertSame('webp', $media->metadata['extension']);
        $this->assertSame('png', $media->metadata['original_extension']);
        $this->assertSame(82, $media->metadata['quality']);
        $this->assertTrue($media->metadata['optimized']);
        $this->assertSame('RIFF', substr(Storage::disk('gcs')->get($media->path), 0, 4));
    }

    #[Test]
    public function authenticated_users_can_download_media(): void
    {
        Storage::fake('gcs');

        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->post(route('posts.store'), [
                'title' => 'First scan appointment',
                'post_type' => TimelinePostType::MILESTONE->value,
                'published_at' => '2026-03-12',
                'media' => [
                    UploadedFile::fake()->create('letter.pdf', 128, 'application/pdf'),
                ],
            ]);

        $media = Post::query()->sole()->media()->sole();

        $this
            ->actingAs($user)
            ->get(route('media.download', $media))
            ->assertOk();
    }

}
