<?php

declare(strict_types=1);

namespace Tests\Unit\Queries;

use App\DTOs\TimelineFiltersDTO;
use App\Enum\TimelinePostType;
use PHPUnit\Framework\TestCase;

class TimelineFiltersTest extends TestCase
{
    public function test_it_normalizes_supported_timeline_filters(): void
    {
        $filters = TimelineFiltersDTO::fromArray([
            'search' => '  First day ',
            'tag' => ' FAMILY ',
            'type' => TimelinePostType::MEMORY->value,
            'author' => ' ALICE ',
        ]);

        $this->assertSame([
            'search' => 'First day',
            'tag' => 'family',
            'type' => TimelinePostType::MEMORY->value,
            'author' => 'alice',
        ], $filters->toArray());
    }

    public function test_it_discards_an_unknown_post_type(): void
    {
        $this->assertSame('', TimelineFiltersDTO::fromArray(['type' => 'unknown'])->type);
    }
}
