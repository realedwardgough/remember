<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class TimelineData
{
    public function __construct(public string $name, public ?string $description)
    {
    }
}
