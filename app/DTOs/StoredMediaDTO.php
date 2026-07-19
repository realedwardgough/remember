<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class StoredMediaDTO
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $path,
        public string $mimeType,
        public int $size,
        public ?int $width,
        public ?int $height,
        public array $metadata,
    ) {
    }
}
