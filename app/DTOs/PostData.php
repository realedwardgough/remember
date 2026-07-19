<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enum\TimelinePostType;

final readonly class PostData
{
    public function __construct(
        public string $title,
        public ?string $content,
        public TimelinePostType $postType,
        public string $publishedAt,
        public ?string $visibility = null,
    ) {
    }
}
