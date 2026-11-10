<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enum\TimelinePostType;
use App\Models\Media;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VideoGalleryTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    #[Test]
    public function gallery_shows_only_videos_with_post_details_and_processing_status_newest_first(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->for($user, 'author')->create([
            'title' => 'First steps', 'post_type' => TimelinePostType::MEMORY,
            'published_at' => '2026-11-01',
        ]);
        $ready = Media::factory()->for($post)->create([
            'original_name' => 'steps.mp4', 'mime_type' => 'video/mp4',
            'metadata' => ['status' => 'ready'], 'created_at' => now()->subDay(),
        ]);
        $processing = Media::factory()->for($post)->create([
            'original_name' => 'birthday.mov', 'mime_type' => 'video/quicktime',
            'metadata' => ['status' => 'processing'], 'created_at' => now(),
        ]);
        Media::factory()->for($post)->create(['mime_type' => 'image/webp']);
        Media::factory()->for($post)->create(['mime_type' => 'application/pdf']);

        $this->actingAs($user)->get(route('videos'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Videos')
                ->has('videos', 2)
                ->where('videos.0.id', $processing->id)
                ->where('videos.0.status', 'processing')
                ->where('videos.0.statusUrl', route('media.status', $processing, false))
                ->where('videos.1.id', $ready->id)
                ->where('videos.1.name', 'steps.mp4')
                ->where('videos.1.mimeType', 'video/mp4')
                ->where('videos.1.status', 'ready')
                ->where('videos.1.url', route('media.show', $ready, false))
                ->where('videos.1.downloadUrl', route('media.download', $ready, false))
                ->where('videos.1.postId', $post->id)
                ->where('videos.1.postTitle', 'First steps')
                ->where('videos.1.postDate', '1 November 2026'));
    }

    #[Test]
    public function private_letter_videos_are_visible_only_to_the_author_and_deleted_posts_are_hidden(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $private = Post::factory()->for($author, 'author')->create(['post_type' => TimelinePostType::LETTER]);
        $video = Media::factory()->for($private)->create(['mime_type' => 'video/mp4']);
        $deleted = Post::factory()->for($author, 'author')->create(['post_type' => TimelinePostType::MEMORY]);
        Media::factory()->for($deleted)->create(['mime_type' => 'video/mp4']);
        $deleted->delete();

        $this->actingAs($viewer)->get(route('videos'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Videos')->has('videos', 0));
        $this->actingAs($author)->get(route('videos'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Videos')
                ->has('videos', 1)->where('videos.0.id', $video->id));
    }

    #[Test]
    public function empty_video_gallery_is_available_to_members_but_not_guests(): void
    {
        $this->get(route('videos'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('videos'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Videos')->has('videos', 0));
    }
}
