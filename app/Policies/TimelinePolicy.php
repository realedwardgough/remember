<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enum\UserRole;
use App\Models\Timeline;
use App\Models\User;

class TimelinePolicy
{
    public function manage(User $user, Timeline $timeline): bool
    {
        return $user->hasRole(UserRole::ADMIN->value);
    }
}
