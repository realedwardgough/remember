<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enum\TimelinePostType;
use App\Models\Media;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use App\Queries\GetSidebarData;
use App\Services\Posts\HashtagService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SidebarCachingTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function sidebar(User $viewer): array
    {
        return app(GetSidebarData::class)->handle($viewer);
    }

    #[Test]
    public function cached_counts_and_tags_are_specific_to_each_viewers_visibility(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $letter = Post::factory()->for($author, 'author')->create(['post_type' => TimelinePostType::LETTER]);
        $letter->tags()->attach(Tag::query()->create(['name' => 'secret']));
        Media::factory()->for($letter)->create();
        $this->assertSame(1, $this->sidebar($author)['memories_count']);
        $this->assertSame(['secret'], $this->sidebar($author)['tags']);
        $this->assertSame(0, $this->sidebar($viewer)['memories_count']);
        $this->assertSame(0, $this->sidebar($viewer)['media_count']);
        $this->assertSame([], $this->sidebar($viewer)['tags']);

        $letter->update(['post_type' => TimelinePostType::MEMORY]);
        $this->assertSame(1, $this->sidebar($viewer)['media_count']);
        $this->assertSame(['secret'], $this->sidebar($viewer)['tags']);
    }

    #[Test]
    public function sidebar_is_reused_and_refreshes_after_posts_media_users_and_tags_change(): void
    {
        $viewer = User::factory()->create(['name' => 'Original Name']);
        $this->sidebar($viewer);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->sidebar($viewer);
        $this->assertSame([], DB::getQueryLog());
        DB::disableQueryLog();

        $post = Post::factory()->for($viewer, 'author')->create(['post_type' => TimelinePostType::MEMORY]);
        $this->assertSame(1, $this->sidebar($viewer)['memories_count']);
        $media = Media::factory()->for($post)->create();
        $this->assertSame(1, $this->sidebar($viewer)['media_count']);
        $media->delete();
        $this->assertSame(0, $this->sidebar($viewer)['media_count']);
        app(HashtagService::class)->sync($post, ['family']);
        $this->assertSame(['family'], $this->sidebar($viewer)['tags']);
        $viewer->update(['name' => 'Updated Name']);
        $this->assertArrayHasKey('Updated', $this->sidebar($viewer)['family']);
        $other = User::factory()->create();
        $this->assertSame(2, $this->sidebar($viewer)['family_count']);
        $other->delete();
        $this->assertSame(1, $this->sidebar($viewer)['family_count']);
        $post->delete();
        $this->assertSame(0, $this->sidebar($viewer)['memories_count']);
        $this->assertSame([], $this->sidebar($viewer)['tags']);
    }

    #[Test]
    public function sidebar_expires_after_a_minute_even_if_updates_bypass_model_events(): void
    {
        $viewer = User::factory()->create();
        $this->assertSame(0, $this->sidebar($viewer)['memories_count']);
        Post::withoutEvents(fn () => Post::factory()->for($viewer, 'author')->create(['post_type' => TimelinePostType::MEMORY]));
        $this->assertSame(0, $this->sidebar($viewer)['memories_count']);
        $this->travel(61)->seconds();
        $this->assertSame(1, $this->sidebar($viewer)['memories_count']);
    }
}
