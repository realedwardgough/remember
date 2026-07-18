<?php

namespace Tests\Feature;

use App\Enum\TimelinePostType;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StorePostTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['filesystems.media_disk' => 'gcs']);

        Cache::flush();
    }

    public function test_authenticated_users_can_store_a_post_with_multiple_media_files(): void
    {
        Storage::fake('gcs');

        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->post(route('posts.store'), [
                'title' => 'First scan appointment',
                'content' => 'A small note for sunshine.',
                'post_type' => TimelinePostType::MILESTONE->value,
                'published_at' => '2026-03-12',
                'media' => [
                    UploadedFile::fake()->image('scan-one.jpg', 1200, 800),
                    UploadedFile::fake()->image('scan-two.jpg', 1000, 700),
                    UploadedFile::fake()->create('letter.pdf', 128, 'application/pdf'),
                ],
            ]);

        $response->assertRedirect(route('home'));

        $post = Post::query()->with('media')->sole();

        $this->assertTrue($post->author->is($user));
        $this->assertSame('First scan appointment', $post->title);
        $this->assertSame(TimelinePostType::MILESTONE, $post->post_type);
        $this->assertSame('2026-03-12', $post->published_at->toDateString());
        $this->assertCount(3, $post->media);
        $this->assertSame([0, 1, 2], $post->media->pluck('sort_order')->all());

        foreach ($post->media as $media) {
            Storage::disk('gcs')->assertExists($media->path);
            $this->assertSame('gcs', $media->disk);
        }

        $this->assertSame('image/webp', $post->media[0]->mime_type);
        $this->assertSame('webp', pathinfo($post->media[0]->path, PATHINFO_EXTENSION));
        $this->assertSame('webp', $post->media[0]->metadata['extension']);
        $this->assertSame('jpg', $post->media[0]->metadata['original_extension']);
        $this->assertTrue($post->media[0]->metadata['optimized']);
        $this->assertNotNull($post->media[0]->width);
        $this->assertNotNull($post->media[0]->height);
        $this->assertSame('application/pdf', $post->media[2]->mime_type);
        $this->assertNull($post->media[2]->width);
        $this->assertNull($post->media[2]->height);
    }



    public function test_home_feed_splits_images_and_files(): void
    {
        Storage::fake('gcs');

        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->post(route('posts.store'), [
                'title' => 'First scan appointment',
                'post_type' => TimelinePostType::MILESTONE->value,
                'published_at' => '2026-03-12',
                'media' => [
                    UploadedFile::fake()->image('scan-one.jpg', 1200, 800),
                    UploadedFile::fake()->create('letter.pdf', 128, 'application/pdf'),
                ],
            ]);

        $this
            ->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                ->where('timelinePosts.data.0.images.0.name', 'scan-one.jpg')
                ->where('timelinePosts.data.0.files.0.name', 'letter.pdf')
                ->where('timelinePosts.data.0.images.0.mimeType', 'image/webp')
                ->where('timelinePosts.data.0.files.0.mimeType', 'application/pdf')
            );
    }

    public function test_uploaded_images_are_converted_to_webp(): void
    {
        Storage::fake('gcs');

        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->post(route('posts.store'), [
                'title' => 'First scan appointment',
                'post_type' => TimelinePostType::MILESTONE->value,
                'published_at' => '2026-03-12',
                'media' => [
                    UploadedFile::fake()->image('scan-one.png', 1200, 800),
                ],
            ]);

        $media = Post::query()->sole()->media()->sole();

        Storage::disk('gcs')->assertExists($media->path);

        $this->assertSame('image/webp', $media->mime_type);
        $this->assertSame('webp', pathinfo($media->path, PATHINFO_EXTENSION));
        $this->assertSame('webp', $media->metadata['extension']);
        $this->assertSame('png', $media->metadata['original_extension']);
        $this->assertSame(82, $media->metadata['quality']);
        $this->assertTrue($media->metadata['optimized']);
        $this->assertSame('RIFF', substr(Storage::disk('gcs')->get($media->path), 0, 4));
    }

    public function test_authenticated_users_can_download_media(): void
    {
        Storage::fake('gcs');

        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->post(route('posts.store'), [
                'title' => 'First scan appointment',
                'post_type' => TimelinePostType::MILESTONE->value,
                'published_at' => '2026-03-12',
                'media' => [
                    UploadedFile::fake()->create('letter.pdf', 128, 'application/pdf'),
                ],
            ]);

        $media = Post::query()->sole()->media()->sole();

        $this
            ->actingAs($user)
            ->get(route('media.download', $media))
            ->assertOk();
    }

    public function test_home_feed_includes_stored_posts(): void
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


    public function test_home_feed_loads_twenty_posts_at_a_time(): void
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


    public function test_home_feed_can_be_searched(): void
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


    public function test_home_feed_can_clear_filters_after_cached_filter_requests(): void
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

    public function test_home_feed_can_be_filtered_by_tag(): void
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

    public function test_home_feed_can_be_filtered_by_author(): void
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

    public function test_home_feed_can_be_filtered_by_post_type(): void
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


    public function test_authenticated_users_can_store_a_letter_post(): void
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

    public function test_letter_posts_are_only_visible_to_the_author_by_default(): void
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
    public function test_no_username_can_bypass_private_letter_visibility(): void
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

    public function test_letter_filter_only_includes_letters_the_viewer_can_see(): void
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


    public function test_hashtags_are_stored_as_tags_and_removed_from_post_content(): void
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

    public function test_post_requires_a_valid_post_type(): void
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

    public function test_guests_cannot_store_posts(): void
    {
        $response = $this->post(route('posts.store'), [
            'title' => 'First scan appointment',
            'post_type' => TimelinePostType::MEMORY->value,
            'published_at' => '2026-03-12',
        ]);

        $response->assertRedirect(route('login'));
    }
}
