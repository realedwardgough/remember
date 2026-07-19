<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enum\TimelinePostType;
use Illuminate\Support\Str;

final readonly class TimelineFiltersDTO
{
    public function __construct(
        public string $search = '',
        public string $tag = '',
        public string $type = '',
        public string $author = '',
    ) {
    }

    /** @param array<string, mixed> $input */
    public static function fromArray(array $input): self
    {
        $type = trim((string) ($input['type'] ?? ''));

        return new self(
            search: trim((string) ($input['search'] ?? '')),
            tag: Str::lower(trim((string) ($input['tag'] ?? ''))),
            type: TimelinePostType::tryFrom($type)?->value ?? '',
            author: Str::lower(trim((string) ($input['author'] ?? ''))),
        );
    }

    /** @return array{search: string, tag: string, type: string, author: string} */
    public function toArray(): array
    {
        return ['search' => $this->search, 'tag' => $this->tag, 'type' => $this->type, 'author' => $this->author];
    }
}
