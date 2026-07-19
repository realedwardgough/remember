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

class LetterVisibilityTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['filesystems.media_disk' => 'gcs']);

        Cache::flush();
    }

    #[Test]
    public function letter_posts_are_only_visible_to_the_author_by_default(): void
    {
        $author = User::factory()->create(['username' => 'aunt']);
        $viewer = User::factory()->create(['username' => 'uncle']);

        $post = Post::factory()->for($author, 'author')->create([
            'title' => 'Private letter',
            'post_type' => TimelinePostType::LETTER,
        ]);

        $post->tags()->attach(Tag::create(['name' => 'secret']));

        $this
            ->actingAs($viewer)
            ->get(route('home'))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                ->has('timelinePosts.data', 0)
                ->where('tags', [])
                ->where('memories_count', 0)
            );
    }
    #[Test]
    public function no_username_can_bypass_private_letter_visibility(): void
    {
        $author = User::factory()->create(['username' => 'letter-author']);
        $viewer = User::factory()->create(['username' => 'administrator']);

        Post::factory()->for($author, 'author')->create([
            'title' => 'Private letter',
            'post_type' => TimelinePostType::LETTER,
        ]);

        $this
            ->actingAs($viewer)
            ->get(route('home'))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page->has('timelinePosts.data', 0)
            );
    }

    #[Test]
    public function letter_filter_only_includes_letters_the_viewer_can_see(): void
    {
        $viewer = User::factory()->create(['username' => 'uncle']);
        $author = User::factory()->create(['username' => 'aunt']);

        Post::factory()->for($viewer, 'author')->create([
            'title' => 'My letter',
            'post_type' => TimelinePostType::LETTER,
        ]);

        Post::factory()->for($author, 'author')->create([
            'title' => 'Other letter',
            'post_type' => TimelinePostType::LETTER,
        ]);

        $this
            ->actingAs($viewer)
            ->get(route('home', ['type' => TimelinePostType::LETTER->value]))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                ->where('filters.type', TimelinePostType::LETTER->value)
                ->has('timelinePosts.data', 1)
                ->where('timelinePosts.data.0.title', 'My letter')
            );
    }


}
