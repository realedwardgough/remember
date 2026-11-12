<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\DTOs\TimelineFiltersDTO;
use App\Enum\TimelinePostType;
use App\Models\Comment;
use App\Models\Heart;
use App\Models\Media;
use App\Models\Post;
use App\Models\User;
use App\Queries\GetTimelinePosts;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TimelineCachingTest extends TestCase
{
    use LazilyRefreshDatabase;

    private User $viewer;
    private Post $post;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->viewer = User::factory()->create();
        $posts = Post::factory()->count(21)->for($this->viewer, 'author')
            ->sequence(fn ($sequence): array => [
                'post_type' => TimelinePostType::MEMORY,
                'published_at' => now()->subDays($sequence->index),
            ])->create();
        $this->post = $posts->first();
    }

    private function page(int $page = 1, ?User $viewer = null): array
    {
        return app(GetTimelinePosts::class)->handle($viewer ?? $this->viewer, new TimelineFiltersDTO, $page);
    }

    private function assertPageIsCached(int $page): void
    {
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->page($page);
        $this->assertSame([], DB::getQueryLog());
        DB::disableQueryLog();
    }

    #[Test]
    public function hearts_refresh_affected_pages_for_each_viewer_but_keep_other_pages_cached(): void
    {
        $other = User::factory()->create();
        $this->page();
        $this->page(1, $other);
        $this->page(2);
        $heart = Heart::query()->create(['post_id' => $this->post->id, 'user_id' => $this->viewer->id]);
        $this->assertPageIsCached(2);
        $mine = $this->page()['data'][0];
        $theirs = $this->page(1, $other)['data'][0];
        $this->assertSame(1, $mine['heartsCount']);
        $this->assertTrue($mine['heartedByViewer']);
        $this->assertFalse($theirs['heartedByViewer']);
        $this->assertSame(1, $theirs['heartsCount']);
        $heart->delete();
        $this->assertPageIsCached(2);
        $this->assertSame(0, $this->page()['data'][0]['heartsCount']);
    }

    #[Test]
    public function comments_and_comment_hearts_refresh_only_pages_containing_the_post(): void
    {
        $this->page();
        $this->page(2);
        $comment = Comment::query()->create([
            'post_id' => $this->post->id, 'author_id' => $this->viewer->id, 'content' => 'Lovely',
        ]);
        $this->assertPageIsCached(2);
        $this->assertSame(1, $this->page()['data'][0]['commentsCount']);
        $heart = $comment->hearts()->create(['user_id' => $this->viewer->id]);
        $this->assertPageIsCached(2);
        $this->assertSame(1, $this->page()['data'][0]['comments'][0]['heartsCount']);
        $heart->delete();
        $this->assertSame(0, $this->page()['data'][0]['comments'][0]['heartsCount']);
        $comment->delete();
        $this->assertPageIsCached(2);
        $this->assertSame(0, $this->page()['data'][0]['commentsCount']);
    }

    #[Test]
    public function media_updates_refresh_only_pages_containing_the_post(): void
    {
        $media = Media::factory()->for($this->post)->create([
            'mime_type' => 'video/mp4', 'thumbnail_path' => null, 'metadata' => ['status' => 'processing'],
        ]);
        $this->page();
        $this->page(2);
        $media->update(['metadata' => ['status' => 'ready'], 'thumbnail_path' => 'preview.jpg']);
        $this->assertPageIsCached(2);
        $this->assertSame('ready', $this->page()['data'][0]['videos'][0]['status']);
        $this->assertNotNull($this->page()['data'][0]['videos'][0]['thumbnailUrl']);
        $media->delete();
        $this->assertSame([], $this->page()['data'][0]['videos']);
    }

    #[Test]
    public function post_edits_deletions_and_creations_refresh_page_membership_and_visibility(): void
    {
        $other = User::factory()->create();
        $this->page(1, $other);
        $this->page(2, $other);
        $this->post->update(['post_type' => TimelinePostType::LETTER]);
        $this->assertNotContains($this->post->id, array_column($this->page(1, $other)['data'], 'id'));
        $this->assertSame(20, $this->page(2, $other)['total']);
        $this->post->delete();
        $this->assertNotContains($this->post->id, array_column($this->page()['data'], 'id'));
        Post::factory()->for($this->viewer, 'author')->create([
            'title' => 'Newest memory', 'post_type' => TimelinePostType::MEMORY, 'published_at' => now()->addDay(),
        ]);
        $this->assertSame('Newest memory', $this->page()['data'][0]['title']);
        $this->assertSame(21, $this->page()['total']);
    }
}
