<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class CreateCommentDTO
{
    public function __construct(
        public int $authorId,
        public string $content,
    ) {
    }

    /** @return array{author_id: int, content: string} */
    public function toArray(): array
    {
        return [
            'author_id' => $this->authorId,
            'content' => $this->content,
        ];
    }
}
