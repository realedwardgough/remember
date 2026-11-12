<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enum\TimelinePostType;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LazyCommentsTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function feed_and_detail_include_counts_but_do_not_fetch_comment_bodies(): void
    {
        $viewer = User::factory()->create();
        $post = Post::factory()->memory()->create();
        Comment::factory()->count(25)->for($post)->create(['content' => 'Loaded only on demand']);
        DB::enableQueryLog();
        foreach (['home', 'posts.show'] as $route) {
            DB::flushQueryLog();
            $prop = $route === 'home' ? 'timelinePosts.data.0' : 'post';
            $this->actingAs($viewer)->get(route($route, $route === 'home' ? [] : [$post]))->assertOk()
                ->assertInertia(fn (Assert $page) => $page->has($prop.'.comments', 0)->where($prop.'.commentsCount', 25));
            $queries = array_column(DB::getQueryLog(), 'query');
            $this->assertEmpty(array_filter($queries, static fn (string $sql): bool => str_starts_with($sql, 'select * from "comments"')));
        }
        DB::disableQueryLog();
    }

    #[Test]
    public function comments_are_paginated_in_order_with_viewer_specific_hearts(): void
    {
        $viewer = User::factory()->create();
        $other = User::factory()->create();
        $post = Post::factory()->memory()->create();
        $comments = Comment::factory()->count(25)->for($post)->create();
        $comments->first()->hearts()->create(['user_id' => $viewer->id]);
        $this->actingAs($viewer)->getJson(route('posts.comments.index', $post))->assertOk()
            ->assertJsonCount(20, 'data')->assertJsonPath('total', 25)
            ->assertJsonPath('data.0.id', $comments->first()->id)->assertJsonPath('data.0.heartsCount', 1)
            ->assertJsonPath('data.0.heartedByViewer', true)->assertJsonPath('next_page', 2)->assertJsonPath('previous_page', null);
        $this->actingAs($other)->getJson(route('posts.comments.index', $post))->assertJsonPath('data.0.heartedByViewer', false);
        $this->getJson(route('posts.comments.index', [$post, 'page' => 2]))->assertJsonCount(5, 'data')
            ->assertJsonPath('data.4.id', $comments->last()->id)->assertJsonPath('next_page', null)->assertJsonPath('previous_page', 1);
        $this->getJson(route('posts.comments.index', [$post, 'focus_id' => $comments->last()->id]))
            ->assertJsonCount(5, 'data')->assertJsonPath('data.4.id', $comments->last()->id);
    }

    #[Test]
    public function comments_require_login_and_visible_non_deleted_interactive_posts(): void
    {
        $author = User::factory()->create();
        $post = Post::factory()->for($author, 'author')->create(['post_type' => TimelinePostType::LETTER]);
        $this->getJson(route('posts.comments.index', $post))->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson(route('posts.comments.index', $post))->assertForbidden();
        $this->actingAs($author)->getJson(route('posts.comments.index', $post))->assertForbidden();
        $post->update(['post_type' => TimelinePostType::MEMORY]);
        $this->getJson(route('posts.comments.index', [$post, 'page' => 0]))->assertUnprocessable();
        $post->delete();
        $this->getJson(route('posts.comments.index', $post))->assertNotFound();
    }
}
