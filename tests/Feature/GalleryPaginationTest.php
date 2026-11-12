<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enum\TimelinePostType;
use App\Models\Media;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GalleryPaginationTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function galleries_return_bounded_stable_pages_with_infinite_scroll_metadata(): void
    {
        $viewer = User::factory()->create();
        $post = Post::factory()->memory()->create();
        foreach (['gallery' => ['image/webp', 'images'], 'videos' => ['video/mp4', 'videos']] as $route => [$type, $prop]) {
            $items = Media::factory()->count(29)->for($post)->create(['mime_type' => $type, 'created_at' => now()]);
            $private = Post::factory()->create(['post_type' => TimelinePostType::LETTER]);
            Media::factory()->for($private)->create(['mime_type' => $type]);
            $first = $this->actingAs($viewer)->get(route($route))->assertOk()
                ->assertInertia(fn (Assert $page) => $page->has($prop.'.data', 24)
                    ->where($prop.'.total', 29)->where($prop.'.data.0.id', $items->last()->id));
            $second = $this->get(route($route, ['page' => 2]))->assertOk()
                ->assertInertia(fn (Assert $page) => $page->has($prop.'.data', 5)->where($prop.'.current_page', 2));
            $firstPage = $first->viewData('page');
            $secondPage = $second->viewData('page');
            $this->assertSame(2, $firstPage['scrollProps'][$prop]['nextPage']);
            $this->assertNull($secondPage['scrollProps'][$prop]['nextPage']);
            $ids = [...array_column($firstPage['props'][$prop]['data'], 'id'), ...array_column($secondPage['props'][$prop]['data'], 'id')];
            $this->assertSame($items->pluck('id')->reverse()->values()->all(), $ids);
        }
    }

    #[Test]
    public function opening_a_post_fetches_siblings_across_gallery_page_boundaries_with_authorization(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $post = Post::factory()->for($author, 'author')->memory()->create();
        Media::factory()->count(26)->for($post)->create(['mime_type' => 'image/webp']);
        Media::factory()->for($post)->create(['mime_type' => 'video/mp4']);
        $this->actingAs($viewer)->getJson(route('posts.media', [$post, 'type' => 'image']))->assertOk()->assertJsonCount(26, 'data');
        $this->getJson(route('posts.media', [$post, 'type' => 'video']))->assertOk()->assertJsonCount(1, 'data');
        $post->update(['post_type' => TimelinePostType::LETTER]);
        $this->getJson(route('posts.media', [$post, 'type' => 'image']))->assertForbidden();
        $this->actingAs($author)->getJson(route('posts.media', [$post, 'type' => 'image']))->assertOk();
        $post->delete();
        $this->getJson(route('posts.media', [$post, 'type' => 'image']))->assertNotFound();
    }
}
