<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PostSchemaTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function posts_table_matches_timeline_post_data(): void
    {
        $this->assertTrue(Schema::hasColumns('posts', [
            'id',
            'author_id',
            'title',
            'content',
            'post_type',
            'published_at',
            'visibility',
            'created_at',
            'updated_at',
        ]));
    }

    #[Test]
    public function media_table_supports_multiple_files_per_post(): void
    {
        $this->assertTrue(Schema::hasColumns('media', [
            'id',
            'post_id',
            'disk',
            'path',
            'thumbnail_path',
            'original_name',
            'mime_type',
            'size',
            'width',
            'height',
            'metadata',
            'sort_order',
            'created_at',
            'updated_at',
        ]));
    }

    #[Test]
    public function tags_table_supports_reusable_post_tags(): void
    {
        $this->assertTrue(Schema::hasColumns('tags', [
            'id',
            'name',
            'created_at',
            'updated_at',
        ]));

        $this->assertTrue(Schema::hasColumns('post_tag', [
            'id',
            'post_id',
            'tag_id',
            'created_at',
            'updated_at',
        ]));
    }

    #[Test]
    public function comments_and_hearts_tables_support_post_engagement(): void
    {
        $this->assertTrue(Schema::hasColumns('comments', [
            'id',
            'post_id',
            'author_id',
            'content',
            'created_at',
            'updated_at',
        ]));

        $this->assertTrue(Schema::hasColumns('hearts', [
            'id',
            'post_id',
            'user_id',
            'created_at',
            'updated_at',
        ]));
    }

}
