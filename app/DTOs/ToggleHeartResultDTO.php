<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class ToggleHeartResultDTO
{
    public function __construct(
        public bool $hearted,
        public ?int $heartId,
    ) {
    }
}
