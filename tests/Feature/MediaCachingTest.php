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
}
