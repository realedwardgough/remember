<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\Posts\HashtagService;
use PHPUnit\Framework\TestCase;

class HashtagServiceTest extends TestCase
{
    public function test_it_extracts_normalized_unique_hashtags(): void
    {
        $service = new HashtagService;

        $this->assertSame(['family', 'first-day'], $service->namesFrom('A #Family moment #family and #First-Day.'));
    }

    public function test_it_removes_hashtags_and_repairs_spacing_and_punctuation(): void
    {
        $service = new HashtagService;

        $this->assertSame('A moment, together.', $service->removeFrom('A #family moment #memory, together.'));
        $this->assertNull($service->removeFrom('#family #memory'));
        $this->assertNull($service->removeFrom(null));
    }
}
