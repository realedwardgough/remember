<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enum\TimelinePostType;
use App\Models\Media;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GalleryTest extends TestCase
{
    use RefreshDatabase;

    public function test_gallery_shows_all_image_media(): void
    {
        $user = User::factory()->create(['name' => 'Alex Morgan', 'username' => 'alex']);
        $post = Post::factory()->for($user, 'author')->create([
            'title' => 'First birthday photos',
            'post_type' => TimelinePostType::MEMORY,
            'published_at' => '2026-04-12',
        ]);

        Media::factory()->for($post)->create([
            'original_name' => 'birthday.jpg',
            'mime_type' => 'image/jpeg',
            'width' => 1200,
            'height' => 800,
            'size' => 123456,
        ]);

        Media::factory()->for($post)->create([
            'original_name' => 'letter.pdf',
            'mime_type' => 'application/pdf',
            'width' => null,
            'height' => null,
        ]);

        $this
            ->actingAs($user)
            ->get(route('gallery'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Gallery')
                ->has('images', 1)
                ->where('images.0.name', 'birthday.jpg')
                ->where('images.0.mimeType', 'image/jpeg')
                ->where('images.0.width', 1200)
                ->where('images.0.height', 800)
                ->where('images.0.postTitle', 'First birthday photos')
                ->where('images.0.postDate', '12 April 2026')
                ->where('filters.search', '')
                ->where('filters.tag', '')
                ->where('filters.type', '')
                ->where('filters.author', '')
                ->where('postTypes', ['Event', 'Milestone', 'Memory', 'Letter'])
                ->where('family.Alex.name', 'Alex Morgan')
                ->where('family_count', 1)
                ->where('media_count', 2)
                ->where('memories_count', 1));
    }

    public function test_gallery_and_media_routes_hide_private_letter_media(): void
    {
        $author = User::factory()->create(['username' => 'aunt']);
        $viewer = User::factory()->create(['username' => 'uncle']);
        $post = Post::factory()->for($author, 'author')->create([
            'title' => 'Private letter',
            'post_type' => TimelinePostType::LETTER,
        ]);

        $media = Media::factory()->for($post)->create([
            'original_name' => 'letter-photo.jpg',
            'mime_type' => 'image/jpeg',
        ]);

        $this
            ->actingAs($viewer)
            ->get(route('gallery'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Gallery')
                ->has('images', 0)
                ->where('media_count', 0)
                ->where('memories_count', 0));

        $this
            ->actingAs($viewer)
            ->get(route('media.show', $media))
            ->assertNotFound();

        $this
            ->actingAs($viewer)
            ->get(route('media.download', $media))
            ->assertNotFound();
    }

    public function test_guests_cannot_view_gallery(): void
    {
        $this
            ->get(route('gallery'))
            ->assertRedirect(route('login'));
    }
}
