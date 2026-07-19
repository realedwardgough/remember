<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Actions\CreatePost;
use App\Actions\UpdatePost;
use App\DTOs\PostData;
use App\Enum\TimelinePostType;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_post_returns_a_post_and_persists_normalized_content_and_tags(): void
    {
        $post = app(CreatePost::class)->handle(
            User::factory()->create(),
            new PostData('First day', 'A #Family memory #family.', TimelinePostType::MEMORY, '2026-07-19'),
        );

        $this->assertInstanceOf(Post::class, $post);
        $this->assertSame('A memory.', $post->content);
        $this->assertSame(['family'], $post->tags->pluck('name')->all());
    }

    public function test_update_post_returns_the_updated_post_and_replaces_tags(): void
    {
        $post = Post::factory()->create(['post_type' => TimelinePostType::MEMORY]);

        $updated = app(UpdatePost::class)->handle(
            $post,
            new PostData('Changed', 'Updated #Milestone', TimelinePostType::MILESTONE, '2026-07-20'),
        );

        $this->assertInstanceOf(Post::class, $updated);
        $this->assertSame('Changed', $updated->title);
        $this->assertSame('Updated', $updated->content);
        $this->assertSame(['milestone'], $updated->tags->pluck('name')->all());
    }
}
