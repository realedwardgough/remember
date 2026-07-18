<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enum\TimelinePostType;
use App\Http\Resources\TimelineResource;
use App\Models\Comment;
use App\Models\Heart;
use App\Models\Media;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimelineResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_matches_the_home_timeline_payload_shape(): void
    {
        $viewer = User::factory()->create();
        $author = User::factory()->create(['username' => 'author-one']);
        $commenter = User::factory()->create(['username' => 'author-two']);

        $post = Post::factory()->for($author, 'author')->create([
            'title' => 'First scan appointment',
            'content' => null,
            'post_type' => TimelinePostType::MILESTONE,
            'published_at' => '2026-03-12',
        ]);

        $post->tags()->attach(Tag::create(['name' => 'scan']));

        Media::factory()->for($post)->create([
            'path' => 'posts/1/scan.webp',
            'original_name' => 'scan.jpg',
            'mime_type' => 'image/webp',
            'size' => 123456,
            'width' => 1200,
            'height' => 800,
            'sort_order' => 0,
        ]);

        Media::factory()->for($post)->create([
            'path' => 'posts/1/letter.pdf',
            'original_name' => null,
            'mime_type' => 'application/pdf',
            'size' => 4096,
            'width' => null,
            'height' => null,
            'sort_order' => 1,
        ]);

        Comment::factory()->for($post)->for($commenter, 'author')->create([
            'content' => 'Lovely update.',
        ]);

        Heart::create([
            'post_id' => $post->id,
            'user_id' => $viewer->id,
        ]);

        $request = request();
        $request->setUserResolver(fn (): User => $viewer);

        $payload = TimelineResource::make(
            Post::query()
                ->with(['author', 'media', 'tags', 'comments.author'])
                ->withCount('hearts')
                ->withExists([
                    'hearts as hearted_by_viewer' => fn (Builder $query): Builder => $query
                        ->where('user_id', $viewer->id),
                ])
                ->sole()
        )->toArray($request);

        $this->assertSame($post->id, $payload['id']);
        $this->assertSame(TimelinePostType::MILESTONE->value, $payload['type']);
        $this->assertSame('First scan appointment', $payload['title']);
        $this->assertSame('', $payload['content']);
        $this->assertSame('12 March 2026', $payload['date']);
        $this->assertSame('2026-03-12', $payload['datetime']);
        $this->assertSame('author-one', $payload['author']);
        $this->assertSame(TimelinePostType::MILESTONE->initial(), $payload['initial']);
        $this->assertSame(['#scan'], $payload['tags']);
        $this->assertSame(1, $payload['commentsCount']);
        $this->assertSame(1, $payload['heartsCount']);
        $this->assertTrue($payload['heartedByViewer']);
        $this->assertFalse($payload['canEdit']);
        $this->assertSame(TimelinePostType::MILESTONE->badgeClass(), $payload['badgeClass']);
        $this->assertSame(TimelinePostType::MILESTONE->typeClass(), $payload['typeClass']);

        $this->assertIsArray($payload['images']);
        $this->assertIsArray($payload['files']);
        $this->assertSame('scan.jpg', $payload['images'][0]['name']);
        $this->assertSame('image/webp', $payload['images'][0]['mimeType']);
        $this->assertSame(1200, $payload['images'][0]['width']);
        $this->assertSame(route('media.show', $payload['images'][0]['id'], false), $payload['images'][0]['url']);

        $this->assertSame('letter.pdf', $payload['files'][0]['name']);
        $this->assertSame('application/pdf', $payload['files'][0]['mimeType']);
        $this->assertNull($payload['files'][0]['width']);
        $this->assertSame(route('media.download', $payload['files'][0]['id'], false), $payload['files'][0]['downloadUrl']);

        $this->assertSame('Lovely update.', $payload['comments'][0]['content']);
        $this->assertSame('author-two', $payload['comments'][0]['author']);
    }
}
