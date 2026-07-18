<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enum\TimelinePostType;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UpdatePostTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_author_can_view_the_edit_page(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->for($user, 'author')->create([
            'title' => 'Original title',
            'content' => 'Original content',
            'post_type' => TimelinePostType::MEMORY,
            'published_at' => '2026-04-04',
        ]);

        $this
            ->actingAs($user)
            ->get(route('posts.edit', $post))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                ->component('posts/Edit')
                ->where('post.id', $post->id)
                ->where('post.title', 'Original title')
                ->where('post.content', 'Original content')
                ->where('post.postType', TimelinePostType::MEMORY->value)
                ->where('post.publishedAt', '2026-04-04')
            );
    }

    public function test_post_author_can_update_the_post_details(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->for($user, 'author')->create([
            'title' => 'Original title',
            'content' => 'Original content',
            'post_type' => TimelinePostType::MEMORY,
            'published_at' => '2026-04-04',
        ]);

        $this
            ->actingAs($user)
            ->patch(route('posts.update', $post), [
                'title' => 'Updated title',
                'content' => 'Updated content with #family',
                'post_type' => TimelinePostType::EVENT->value,
                'published_at' => '2026-05-30',
            ])
            ->assertRedirect(route('home'));

        $post->refresh()->load('tags');

        $this->assertSame('Updated title', $post->title);
        $this->assertSame('Updated content with', $post->content);
        $this->assertSame(TimelinePostType::EVENT, $post->post_type);
        $this->assertSame('2026-05-30', $post->published_at->toDateString());
        $this->assertDatabaseHas('tags', ['name' => 'family']);
        $this->assertTrue($post->tags->contains(fn (Tag $tag): bool => $tag->name === 'family'));
    }


    public function test_post_author_can_soft_delete_the_post(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->for($user, 'author')->create([
            'title' => 'Delete me',
            'published_at' => '2026-04-04',
        ]);

        $this
            ->actingAs($user)
            ->delete(route('posts.destroy', $post))
            ->assertRedirect(route('home'));

        $this->assertSoftDeleted($post);

        $this
            ->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                ->has('timelinePosts.data', 0)
            );
    }

    public function test_non_author_cannot_view_update_or_delete_the_post(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $post = Post::factory()->for($author, 'author')->create([
            'title' => 'Original title',
            'content' => 'Original content',
            'post_type' => TimelinePostType::MILESTONE,
            'published_at' => '2026-04-04',
        ]);

        $this
            ->actingAs($viewer)
            ->get(route('posts.edit', $post))
            ->assertForbidden();

        $this
            ->actingAs($viewer)
            ->patch(route('posts.update', $post), [
                'title' => 'Updated title',
                'content' => 'Updated content',
                'post_type' => TimelinePostType::EVENT->value,
                'published_at' => '2026-05-30',
            ])
            ->assertForbidden();

        $this
            ->actingAs($viewer)
            ->delete(route('posts.destroy', $post))
            ->assertForbidden();

        $this->assertNotSoftDeleted($post);
    }
}
