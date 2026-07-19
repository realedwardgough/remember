<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use App\Enum\TimelinePostType;
use App\Models\Comment;
use App\Models\CommentHeart;
use App\Models\Heart;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CommentAndHeartTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function authenticated_users_can_comment_on_posts(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->memory()->create();

        $this
            ->actingAs($user)
            ->post(route('posts.comments.store', $post), [
                'content' => 'This is such a lovely update.',
            ])
            ->assertRedirect();

        $comment = Comment::query()->sole();

        $this->assertTrue($comment->post->is($post));
        $this->assertTrue($comment->author->is($user));
        $this->assertSame('This is such a lovely update.', $comment->content);
    }

    #[Test]
    public function comments_require_content(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->memory()->create();

        $this
            ->actingAs($user)
            ->post(route('posts.comments.store', $post), [
                'content' => '',
            ])
            ->assertSessionHasErrors('content');
    }

    #[Test]
    public function authenticated_users_can_toggle_a_post_heart(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->memory()->create();

        $this
            ->actingAs($user)
            ->post(route('posts.hearts.toggle', $post))
            ->assertRedirect();

        $this->assertDatabaseHas('hearts', [
            'post_id' => $post->id,
            'user_id' => $user->id,
        ]);

        $this
            ->actingAs($user)
            ->post(route('posts.hearts.toggle', $post))
            ->assertRedirect();

        $this->assertDatabaseMissing('hearts', [
            'post_id' => $post->id,
            'user_id' => $user->id,
        ]);
    }


    #[Test]
    public function authenticated_users_can_toggle_a_comment_heart(): void
    {
        $user = User::factory()->create();
        $comment = Comment::factory()->for(Post::factory()->memory())->create();

        $this
            ->actingAs($user)
            ->post(route('comments.hearts.toggle', $comment))
            ->assertRedirect();

        $this->assertDatabaseHas('comment_hearts', [
            'comment_id' => $comment->id,
            'user_id' => $user->id,
        ]);

        $this
            ->actingAs($user)
            ->post(route('comments.hearts.toggle', $comment))
            ->assertRedirect();

        $this->assertDatabaseMissing('comment_hearts', [
            'comment_id' => $comment->id,
            'user_id' => $user->id,
        ]);
    }

    #[Test]
    public function home_feed_includes_comments_and_heart_state(): void
    {
        $viewer = User::factory()->create(['username' => 'viewer']);
        $commenter = User::factory()->create(['username' => 'author-one']);
        $post = Post::factory()->create([
            'title' => 'First little kick',
            'post_type' => TimelinePostType::MILESTONE,
            'published_at' => '2026-04-04',
        ]);

        $comments = Comment::factory()
            ->count(4)
            ->for($post)
            ->for($commenter, 'author')
            ->sequence(fn (\Illuminate\Database\Eloquent\Factories\Sequence $sequence): array => [
                'content' => 'Comment '.($sequence->index + 1),
                'created_at' => now()->addMinutes($sequence->index),
            ])
            ->create();

        CommentHeart::create([
            'comment_id' => $comments->first()->id,
            'user_id' => $viewer->id,
        ]);

        CommentHeart::create([
            'comment_id' => $comments->first()->id,
            'user_id' => $commenter->id,
        ]);

        Heart::create([
            'post_id' => $post->id,
            'user_id' => $viewer->id,
        ]);

        $this
            ->actingAs($viewer)
            ->get(route('home'))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                ->where('timelinePosts.data.0.title', 'First little kick')
                ->where('timelinePosts.data.0.heartsCount', 1)
                ->where('timelinePosts.data.0.heartedByViewer', true)
                ->where('timelinePosts.data.0.commentsCount', 4)
                ->has('timelinePosts.data.0.comments', 4)
                ->where('timelinePosts.data.0.comments.0.content', 'Comment 1')
                ->where('timelinePosts.data.0.comments.0.author', 'author-one')
                ->where('timelinePosts.data.0.comments.0.heartsCount', 2)
                ->where('timelinePosts.data.0.comments.0.heartedByViewer', true)
            );
    }

    #[Test]
    public function guests_cannot_comment_or_heart_posts(): void
    {
        $post = Post::factory()->memory()->create();

        $this
            ->post(route('posts.comments.store', $post), ['content' => 'Hello'])
            ->assertRedirect(route('login'));

        $comment = Comment::factory()->for($post)->create();

        $this
            ->post(route('posts.hearts.toggle', $post))
            ->assertRedirect(route('login'));

        $this
            ->post(route('comments.hearts.toggle', $comment))
            ->assertRedirect(route('login'));
    }

    #[Test]
    public function private_letters_reject_comment_and_heart_endpoints(): void
    {
        $user = User::factory()->create();
        $letter = Post::factory()->create([
            'post_type' => TimelinePostType::LETTER,
        ]);

        $this->actingAs($user)
            ->post(route('posts.comments.store', $letter), ['content' => 'Not allowed.'])
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('posts.hearts.toggle', $letter))
            ->assertForbidden();

        $comment = Comment::factory()->for($letter)->create();

        $this->actingAs($user)
            ->post(route('comments.hearts.toggle', $comment))
            ->assertForbidden();

        $this->assertDatabaseCount('comments', 1);
        $this->assertDatabaseEmpty('hearts');
        $this->assertDatabaseEmpty('comment_hearts');
    }
}
