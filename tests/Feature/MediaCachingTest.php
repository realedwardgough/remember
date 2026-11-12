<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enum\TimelinePostType;
use App\Models\Media;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MediaCachingTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['media-cache.enabled' => false]);
    }

    private function photo(User $author): Media
    {
        Storage::fake('gcs');
        $post = Post::factory()->for($author, 'author')->create(['post_type' => TimelinePostType::MEMORY]);
        Storage::disk('gcs')->put('posts/photo.webp', 'photo-content');

        return Media::factory()->for($post)->create([
            'disk' => 'gcs', 'path' => 'posts/photo.webp',
            'mime_type' => 'image/webp', 'size' => strlen('photo-content'),
        ]);
    }

    #[Test]
    public function photos_can_be_cached_privately_but_require_revalidation(): void
    {
        $user = User::factory()->create();
        $media = $this->photo($user);
        $response = $this->actingAs($user)->get(route('media.show', $media))
            ->assertOk()->assertHeader('Content-Type', 'image/webp')
            ->assertHeader('Content-Length', (string) $media->size)
            ->assertHeader('ETag')->assertHeader('Last-Modified')
            ->assertStreamedContent('photo-content');

        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
        $this->assertStringNotContainsString('no-store', $cacheControl);
    }

    #[Test]
    public function unchanged_photos_return_304_without_contacting_cloud_storage(): void
    {
        $user = User::factory()->create();
        $media = $this->photo($user);
        $first = $this->actingAs($user)->get(route('media.show', $media))->assertOk();
        Storage::shouldReceive('disk')->never();

        $this->get(route('media.show', $media), ['If-None-Match' => $first->headers->get('ETag')])
            ->assertStatus(304)->assertContent('')->assertHeader('ETag', $first->headers->get('ETag'));
        $this->get(route('media.show', $media), ['If-Modified-Since' => $first->headers->get('Last-Modified')])
            ->assertStatus(304)->assertContent('');
    }

    #[Test]
    public function changed_photo_content_invalidates_the_cached_copy(): void
    {
        $user = User::factory()->create();
        $media = $this->photo($user);
        $first = $this->actingAs($user)->get(route('media.show', $media))->assertOk();
        Storage::disk('gcs')->put('posts/replacement.webp', 'updated-photo');
        $media->update(['path' => 'posts/replacement.webp', 'size' => strlen('updated-photo')]);

        $response = $this->get(route('media.show', $media), ['If-None-Match' => $first->headers->get('ETag')])
            ->assertOk()->assertStreamedContent('updated-photo');
        $this->assertNotSame($first->headers->get('ETag'), $response->headers->get('ETag'));
    }

    #[Test]
    public function cached_validators_do_not_bypass_post_visibility_or_deletion(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $media = $this->photo($author);
        $first = $this->actingAs($viewer)->get(route('media.show', $media))->assertOk();
        $media->post->update(['post_type' => TimelinePostType::LETTER]);
        Storage::shouldReceive('disk')->never();

        $this->get(route('media.show', $media), ['If-None-Match' => $first->headers->get('ETag')])->assertNotFound();
        $media->post->delete();
        $this->actingAs($author)->get(route('media.show', $media), ['If-None-Match' => $first->headers->get('ETag')])->assertNotFound();
    }

    #[Test]
    public function validators_are_specific_to_the_authenticated_user(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $media = $this->photo($author);
        $first = $this->actingAs($author)->get(route('media.show', $media))->assertOk();

        $response = $this->actingAs($viewer)->get(route('media.show', $media), ['If-None-Match' => $first->headers->get('ETag')])->assertOk();
        $this->assertNotSame($first->headers->get('ETag'), $response->headers->get('ETag'));
    }

    #[Test]
    public function thumbnails_revalidate_without_reading_storage_and_change_with_the_preview(): void
    {
        $user = User::factory()->create();
        $media = $this->photo($user);
        $media->update(['mime_type' => 'video/mp4', 'thumbnail_path' => 'preview.jpg']);
        Storage::disk('gcs')->put('preview.jpg', 'preview');
        $first = $this->actingAs($user)->get(route('media.thumbnail', $media))
            ->assertOk()->assertHeader('ETag')->assertHeader('Last-Modified')
            ->assertHeader('Content-Type', 'image/jpeg')->assertStreamedContent('preview');
        $this->assertStringNotContainsString('no-store', $first->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $first->headers->get('Cache-Control'));
        Storage::disk('gcs')->put('new-preview.jpg', 'new-preview');
        $media->update(['thumbnail_path' => 'new-preview.jpg']);
        $next = $this->get(route('media.thumbnail', $media), ['If-None-Match' => $first->headers->get('ETag')])
            ->assertOk()->assertStreamedContent('new-preview');
        $this->assertNotSame($first->headers->get('ETag'), $next->headers->get('ETag'));
        Storage::shouldReceive('disk')->never();
        $this->get(route('media.thumbnail', $media), ['If-None-Match' => $next->headers->get('ETag')])
            ->assertStatus(304)->assertContent('');
    }

    #[Test]
    public function thumbnail_validators_cannot_bypass_visibility_and_are_user_specific(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $media = $this->photo($author);
        $media->update(['mime_type' => 'video/mp4', 'thumbnail_path' => 'preview.jpg']);
        $first = $this->actingAs($author)->get(route('media.thumbnail', $media))->assertOk();
        $this->actingAs($viewer)->get(route('media.thumbnail', $media), ['If-None-Match' => $first->headers->get('ETag')])
            ->assertOk();
        $media->post->update(['post_type' => TimelinePostType::LETTER]);
        Storage::shouldReceive('disk')->never();
        $this->get(route('media.thumbnail', $media), ['If-None-Match' => $first->headers->get('ETag')])->assertNotFound();
        $media->post->delete();
        $this->actingAs($author)->get(route('media.thumbnail', $media), ['If-None-Match' => $first->headers->get('ETag')])->assertNotFound();
    }

    #[Test]
    public function head_requests_do_not_download_photos_or_thumbnails(): void
    {
        $user = User::factory()->create();
        $media = $this->photo($user);
        Storage::shouldReceive('disk')->never();
        $this->actingAs($user)->head(route('media.show', $media))
            ->assertOk()->assertContent('')->assertHeader('Content-Length', (string) $media->size);
        $media->update(['mime_type' => 'video/mp4', 'thumbnail_path' => 'preview.jpg']);
        $this->head(route('media.thumbnail', $media))->assertOk()->assertContent('')->assertHeader('ETag');
    }
}
