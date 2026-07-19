<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class SetupData
{
    public function __construct(
        public string $timelineName,
        public ?string $timelineDescription,
        public string $name,
        public string $username,
        public string $email,
        public string $password,
    ) {
    }
}
