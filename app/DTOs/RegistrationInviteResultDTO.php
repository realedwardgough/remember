<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Models\RegistrationInvite;

final readonly class RegistrationInviteResultDTO
{
    public function __construct(
        public RegistrationInvite $invite,
        public string $token,
        public string $url,
    ) {
    }
}
