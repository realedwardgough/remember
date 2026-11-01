<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\User;

class UserSettingsObserver
{
    public function deleted(User $user): void
    {
        $settings = $user->settings();

        foreach (array_keys($settings->all()) as $key) {
            $settings->forget($key);
        }
    }
}
