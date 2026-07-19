<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use App\Enum\TimelinePostType;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StorePostTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['filesystems.media_disk' => 'gcs']);

        Cache::flush();
    }

    #[Test]
    public function authenticated_users_can_store_a_letter_post(): void
    {
        $user = User::factory()->create(['username' => 'aunt']);

        $this
            ->actingAs($user)
            ->post(route('posts.store'), [
                'title' => 'Dear sunshine',
                'content' => 'I cannot wait to tell you about today.',
                'post_type' => TimelinePostType::LETTER->value,
                'published_at' => '2026-05-01',
            ])
            ->assertRedirect(route('home'));

        $post = Post::query()->sole();

        $this->assertSame(TimelinePostType::LETTER, $post->post_type);
        $this->assertTrue($post->author->is($user));

        $this
            ->actingAs($user)
            ->get(route('home', ['type' => TimelinePostType::LETTER->value]))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                ->where('filters.type', TimelinePostType::LETTER->value)
                ->has('timelinePosts.data', 1)
                ->where('timelinePosts.data.0.title', 'Dear sunshine')
                ->where('timelinePosts.data.0.type', TimelinePostType::LETTER->value)
            );
    }

    #[Test]
    public function hashtags_are_stored_as_tags_and_removed_from_post_content(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->post(route('posts.store'), [
                'title' => 'A little update',
                'content' => 'A lovely morning #Family #first-kick #Family and smiles #sunshine.',
                'post_type' => TimelinePostType::MEMORY->value,
                'published_at' => '2026-03-12',
            ])
            ->assertRedirect(route('home'));

        $post = Post::query()->with('tags')->sole();

        $this->assertSame('A lovely morning and smiles.', $post->content);
        $this->assertSame(['family', 'first-kick', 'sunshine'], $post->tags->pluck('name')->all());
        $this->assertDatabaseCount('tags', 3);

        $this
            ->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                ->where('timelinePosts.data.0.content', 'A lovely morning and smiles.')
                ->where('timelinePosts.data.0.tags', ['#family', '#first-kick', '#sunshine'])
            );
    }

    #[Test]
    public function post_requires_a_valid_post_type(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->post(route('posts.store'), [
                'title' => 'First scan appointment',
                'post_type' => 'Photo',
                'published_at' => '2026-03-12',
            ]);

        $response->assertSessionHasErrors('post_type');
    }

    #[Test]
    public function guests_cannot_store_posts(): void
    {
        $response = $this->post(route('posts.store'), [
            'title' => 'First scan appointment',
            'post_type' => TimelinePostType::MEMORY->value,
            'published_at' => '2026-03-12',
        ]);

        $response->assertRedirect(route('login'));
    }
}
