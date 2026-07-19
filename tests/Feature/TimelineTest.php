<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use App\Enum\TimelinePostType;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TimelineTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['filesystems.media_disk' => 'gcs']);

        Cache::flush();
    }

    #[Test]
    public function home_feed_includes_stored_posts(): void
    {
        $user = User::factory()->create();

        Post::factory()->for($user, 'author')->create([
            'title' => 'Nursery ideas coming together',
            'content' => 'A note about the room.',
            'post_type' => TimelinePostType::MEMORY,
            'published_at' => '2026-04-04',
        ]);

        $this
            ->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                ->component('Home')
                ->where('timelinePosts.data.0.title', 'Nursery ideas coming together')
                ->where('timelinePosts.data.0.type', TimelinePostType::MEMORY->value)
                ->where('timelinePosts.data.0.author', $user->username)
                ->where('timelinePosts.data.0.datetime', '2026-04-04')
                ->where('timelinePosts.data.0.canEdit', true)
            );
    }


    #[Test]
    public function home_feed_loads_twenty_posts_at_a_time(): void
    {
        $user = User::factory()->create();

        Post::factory()
            ->count(21)
            ->for($user, 'author')
            ->sequence(fn (\Illuminate\Database\Eloquent\Factories\Sequence $sequence): array => [
                'title' => 'Timeline post '.($sequence->index + 1),
                'published_at' => now()->subDays($sequence->index),
            ])
            ->create();

        $this
            ->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                ->has('timelinePosts.data', 20)
                ->where('timelinePosts.data.0.title', 'Timeline post 1')
            );
    }


    #[Test]
    public function home_feed_can_be_searched(): void
    {
        $user = User::factory()->create();

        Post::factory()->for($user, 'author')->create([
            'title' => 'First scan appointment',
            'content' => 'A quiet morning together.',
        ]);

        Post::factory()->for($user, 'author')->create([
            'title' => 'Nursery paint samples',
            'content' => 'Choosing colours for the room.',
        ]);

        $this
            ->actingAs($user)
            ->get(route('home', ['search' => 'scan']))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                ->where('filters.search', 'scan')
                ->has('timelinePosts.data', 1)
                ->where('timelinePosts.data.0.title', 'First scan appointment')
            );
    }


    #[Test]
    public function home_feed_can_clear_filters_after_cached_filter_requests(): void
    {
        $user = User::factory()->create();

        Post::factory()->for($user, 'author')->create([
            'title' => 'First scan appointment',
            'content' => 'A quiet morning together.',
        ]);

        Post::factory()->for($user, 'author')->create([
            'title' => 'Nursery paint samples',
            'content' => 'Choosing colours for the room.',
        ]);

        $this
            ->actingAs($user)
            ->get(route('home', ['search' => 'scan']))
            ->assertOk();

        $this
            ->actingAs($user)
            ->get(route('home', ['search' => 'scan']))
            ->assertOk();

        $this
            ->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                ->where('filters.search', '')
                ->has('timelinePosts.data', 2)
            );
    }

    #[Test]
    public function home_feed_can_be_filtered_by_tag(): void
    {
        $user = User::factory()->create();
        $family = Tag::create(['name' => 'family']);
        $scan = Tag::create(['name' => 'scan']);

        $familyPost = Post::factory()->for($user, 'author')->create([
            'title' => 'Family breakfast',
        ]);

        $scanPost = Post::factory()->for($user, 'author')->create([
            'title' => 'Scan day',
        ]);

        $familyPost->tags()->attach($family);
        $scanPost->tags()->attach($scan);

        $this
            ->actingAs($user)
            ->get(route('home', ['tag' => 'scan']))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                ->where('filters.tag', 'scan')
                ->where('tags', ['family', 'scan'])
                ->has('timelinePosts.data', 1)
                ->where('timelinePosts.data.0.title', 'Scan day')
                ->where('timelinePosts.data.0.tags', ['#scan'])
            );
    }

    #[Test]
    public function home_feed_can_be_filtered_by_author(): void
    {
        $viewer = User::factory()->create();
        $firstAuthor = User::factory()->create(['username' => 'author-one']);
        $secondAuthor = User::factory()->create(['username' => 'author-two']);

        Post::factory()->for($firstAuthor, 'author')->create([
            'title' => 'First update',
            'post_type' => TimelinePostType::MEMORY,
        ]);

        Post::factory()->for($secondAuthor, 'author')->create([
            'title' => 'Second update',
            'post_type' => TimelinePostType::MEMORY,
        ]);

        $this
            ->actingAs($viewer)
            ->get(route('home', ['author' => 'author-one']))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                ->where('filters.author', 'author-one')
                ->has('timelinePosts.data', 1)
                ->where('timelinePosts.data.0.title', 'First update')
                ->where('timelinePosts.data.0.author', 'author-one')
            );
    }

    #[Test]
    public function home_feed_can_be_filtered_by_post_type(): void
    {
        $user = User::factory()->create();

        Post::factory()->for($user, 'author')->create([
            'title' => 'First little kick',
            'post_type' => TimelinePostType::MILESTONE,
        ]);

        Post::factory()->for($user, 'author')->create([
            'title' => 'A weekend note',
            'post_type' => TimelinePostType::MEMORY,
        ]);

        $this
            ->actingAs($user)
            ->get(route('home', ['type' => TimelinePostType::MILESTONE->value]))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                ->where('filters.type', TimelinePostType::MILESTONE->value)
                ->where('postTypes', [
                    TimelinePostType::EVENT->value,
                    TimelinePostType::MILESTONE->value,
                    TimelinePostType::MEMORY->value,
                    TimelinePostType::LETTER->value,
                ])
                ->has('timelinePosts.data', 1)
                ->where('timelinePosts.data.0.title', 'First little kick')
                ->where('timelinePosts.data.0.type', TimelinePostType::MILESTONE->value)
            );
    }


}
