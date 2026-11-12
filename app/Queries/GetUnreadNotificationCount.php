<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\ActivityNotification;
use App\Models\User;

class GetUnreadNotificationCount
{
    public function handle(User $user): int
    {
        return ActivityNotification::query()->visibleTo($user)->whereNull('read_at')->count();
    }
}
